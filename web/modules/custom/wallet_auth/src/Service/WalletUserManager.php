<?php

declare(strict_types=1);

namespace Drupal\wallet_auth\Service;

use Drupal\Core\Database\Connection;
use Drupal\externalauth\ExternalAuthInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\user\UserInterface;

/**
 * Service for managing wallet-to-user mapping and user creation.
 */
class WalletUserManager {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The external authentication service.
   *
   * @var \Drupal\externalauth\ExternalAuthInterface
   */
  protected $externalAuth;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The time service.
   *
   * @var \Drupal\Core\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a WalletUserManager service.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\externalauth\ExternalAuthInterface $external_auth
   *   The external authentication service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   * @param \Drupal\Core\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    Connection $database,
    ExternalAuthInterface $external_auth,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger_factory,
    TimeInterface $time
  ) {
    $this->database = $database;
    $this->externalAuth = $external_auth;
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger_factory->get('wallet_auth');
    $this->time = $time;
  }

  /**
   * Load a user by wallet address.
   *
   * @param string $walletAddress
   *   The wallet address.
   *
   * @return \Drupal\user\UserInterface|null
   *   The user entity if found, NULL otherwise.
   */
  public function loadUserByWalletAddress(string $walletAddress): ?UserInterface {
    try {
      $query = $this->database->select('wallet_auth_wallet_address', 'wa')
        ->fields('wa', ['uid'])
        ->condition('wa.wallet_address', $walletAddress)
        ->condition('wa.status', 1)
        ->range(0, 1);

      $uid = $query->execute()->fetchField();

      if (!$uid) {
        return NULL;
      }

      $user = $this->entityTypeManager->getStorage('user')->load($uid);

      if ($user && $user->isActive()) {
        return $user;
      }

      return NULL;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to load user by wallet address: @message', ['@message' => $e->getMessage()]);
      return NULL;
    }
  }

  /**
   * Link a wallet address to a user.
   *
   * @param string $walletAddress
   *   The wallet address.
   * @param int $uid
   *   The user ID.
   */
  public function linkWalletToUser(string $walletAddress, int $uid): void {
    try {
      $currentTime = $this->time->getRequestTime();

      // Check if wallet is already linked to a different user
      $existingUid = $this->database->select('wallet_auth_wallet_address', 'wa')
        ->fields('wa', ['uid'])
        ->condition('wa.wallet_address', $walletAddress)
        ->range(0, 1)
        ->execute()
        ->fetchField();

      if ($existingUid && $existingUid != $uid) {
        $this->logger->warning('Wallet @wallet already linked to user @uid, cannot relink', [
          '@wallet' => $walletAddress,
          '@uid' => $existingUid,
        ]);
        return;
      }

      // Insert or update the wallet link
      $this->database->merge('wallet_auth_wallet_address')
        ->key('wallet_address', $walletAddress)
        ->fields([
          'uid' => $uid,
          'created' => $existingUid ? $this->getWalletCreatedTime($walletAddress) : $currentTime,
          'last_used' => $currentTime,
          'status' => 1,
        ])
        ->execute();

      $this->logger->info('Linked wallet @wallet to user @uid', [
        '@wallet' => $walletAddress,
        '@uid' => $uid,
      ]);
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to link wallet to user: @message', ['@message' => $e->getMessage()]);
      throw $e;
    }
  }

  /**
   * Get the created time for a wallet address.
   *
   * @param string $walletAddress
   *   The wallet address.
   *
   * @return int
   *   The created timestamp, or current time if not found.
   */
  protected function getWalletCreatedTime(string $walletAddress): int {
    $created = $this->database->select('wallet_auth_wallet_address', 'wa')
      ->fields('wa', ['created'])
      ->condition('wa.wallet_address', $walletAddress)
      ->range(0, 1)
      ->execute()
      ->fetchField();

    return $created ?: $this->time->getRequestTime();
  }

  /**
   * Create a new user from a wallet address.
   *
   * @param string $walletAddress
   *   The wallet address.
   *
   * @return \Drupal\user\UserInterface
   *   The created user.
   */
  public function createUserFromWallet(string $walletAddress): UserInterface {
    $username = $this->generateUsername($walletAddress);
    $email = $username . '@wallet.local';

    // Use External Auth to register the user
    $account = $this->externalAuth->register($username, 'wallet_auth');
    $account->setEmail($email);
    $account->activate();
    $account->save();

    // Link wallet to user
    $this->linkWalletToUser($walletAddress, (int) $account->id());

    $this->logger->info('Created new user @username from wallet @wallet', [
      '@username' => $username,
      '@wallet' => $walletAddress,
    ]);

    return $account;
  }

  /**
   * Generate a unique username from a wallet address.
   *
   * @param string $walletAddress
   *   The wallet address.
   *
   * @return string
   *   A unique username.
   */
  protected function generateUsername(string $walletAddress): string {
    $baseUsername = 'wallet_' . substr($walletAddress, 2, 8);
    $username = $baseUsername;
    $counter = 0;

    // Ensure username is unique
    while ($this->usernameExists($username)) {
      $counter++;
      $username = $baseUsername . '_' . $counter;
    }

    return $username;
  }

  /**
   * Check if a username already exists.
   *
   * @param string $username
   *   The username to check.
   *
   * @return bool
   *   TRUE if username exists, FALSE otherwise.
   */
  protected function usernameExists(string $username): bool {
    return (bool) $this->database->select('users_field_data', 'u')
      ->fields('u', ['uid'])
      ->condition('u.name', $username)
      ->range(0, 1)
      ->execute()
      ->fetchField();
  }

  /**
   * Login or create a user from a wallet address.
   *
   * This is the main authentication flow that either loads an existing user
   * or creates a new one.
   *
   * @param string $walletAddress
   *   The wallet address.
   *
   * @return \Drupal\user\UserInterface
   *   The logged-in or newly created user.
   */
  public function loginOrCreateUser(string $walletAddress): UserInterface {
    // First try to load existing user
    $existingUser = $this->loadUserByWalletAddress($walletAddress);

    if ($existingUser) {
      // Update last_used timestamp
      $this->database->update('wallet_auth_wallet_address')
        ->fields(['last_used' => $this->time->getRequestTime()])
        ->condition('wallet_address', $walletAddress)
        ->execute();

      $this->logger->info('Logging in existing user @uid for wallet @wallet', [
        '@uid' => $existingUser->id(),
        '@wallet' => $walletAddress,
      ]);

      return $existingUser;
    }

    // Create new user
    return $this->createUserFromWallet($walletAddress);
  }

  /**
   * Get all wallet addresses for a user.
   *
   * @param int $uid
   *   The user ID.
   *
   * @return array
   *   Array of wallet address strings.
   */
  public function getUserWallets(int $uid): array {
    try {
      return $this->database->select('wallet_auth_wallet_address', 'wa')
        ->fields('wa', ['wallet_address'])
        ->condition('wa.uid', $uid)
        ->condition('wa.status', 1)
        ->execute()
        ->fetchCol();
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to get user wallets: @message', ['@message' => $e->getMessage()]);
      return [];
    }
  }

}
