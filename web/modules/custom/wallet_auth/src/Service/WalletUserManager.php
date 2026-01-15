<?php

declare(strict_types=1);

namespace Drupal\wallet_auth\Service;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\externalauth\ExternalAuthInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\user\UserInterface;
use Drupal\wallet_auth\Entity\WalletAddressInterface;
use Drupal\wallet_auth\Event\WalletAuthEvents;
use Drupal\wallet_auth\Event\WalletLinkedEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Service for managing wallet-to-user mapping and user creation.
 */
class WalletUserManager implements WalletUserManagerInterface {

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
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Constructs a WalletUserManager service.
   *
   * @param \Drupal\externalauth\ExternalAuthInterface $external_auth
   *   The external authentication service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   */
  public function __construct(
    ExternalAuthInterface $external_auth,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger_factory,
    TimeInterface $time,
    EventDispatcherInterface $event_dispatcher,
  ) {
    $this->externalAuth = $external_auth;
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger_factory->get('wallet_auth');
    $this->time = $time;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * Gets the wallet address entity storage.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   *   The wallet address storage.
   */
  protected function getWalletStorage(): EntityStorageInterface {
    return $this->entityTypeManager->getStorage('wallet_address');
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
      /** @var \Drupal\wallet_auth\Entity\WalletAddressInterface[] $wallets */
      $wallets = $this->getWalletStorage()->loadByProperties([
        'wallet_address' => $walletAddress,
      ]);

      $wallet = reset($wallets);
      if (!$wallet) {
        return NULL;
      }

      // Check if wallet is active.
      if (!$wallet->isActive()) {
        return NULL;
      }

      $user = $wallet->getOwner();

      // Handle orphaned wallets where user was deleted.
      // Return NULL to allow reassignment to new user.
      // The linkWalletToUser() method will handle reassigning this wallet.
      if (!$user || !$user->id()) {
        return NULL;
      }

      // Also check if user is active.
      if ($user->isActive()) {
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
      $storage = $this->getWalletStorage();
      $currentTime = $this->time->getRequestTime();

      // Check if wallet already exists.
      /** @var \Drupal\wallet_auth\Entity\WalletAddressInterface[] $wallets */
      $wallets = $storage->loadByProperties([
        'wallet_address' => $walletAddress,
      ]);
      $wallet = reset($wallets);

      $isNew = FALSE;

      if (!$wallet) {
        // Create new wallet entity.
        $wallet = $storage->create([
          'wallet_address' => $walletAddress,
          'uid' => $uid,
          'created' => $currentTime,
          'last_used' => $currentTime,
          'status' => 1,
        ]);
        $isNew = TRUE;
      }
      else {
        // Check if the current owner still exists.
        $currentOwner = $wallet->getOwner();
        $ownerExists = $currentOwner && $currentOwner->id();

        // Check if wallet is linked to a different user.
        if ($ownerExists && $wallet->getOwnerId() != $uid) {
          // Wallet is linked to a different active user - cannot reassign.
          $this->logger->warning('Attempted to link wallet @wallet to user @uid, but already linked to @existing', [
            '@wallet' => $walletAddress,
            '@uid' => $uid,
            '@existing' => $wallet->getOwnerId(),
          ]);
          return;
        }

        // If owner was deleted, reassign the wallet to new user.
        if (!$ownerExists) {
          $this->logger->notice('Reassigning orphaned wallet @wallet to user @uid (previous owner deleted)', [
            '@wallet' => $walletAddress,
            '@uid' => $uid,
          ]);
          $wallet->setOwnerId($uid);
          $isNew = TRUE;
        }

        // Update existing wallet.
        $wallet->setLastUsedTime($currentTime);
        $wallet->setActive(TRUE);
      }

      $wallet->save();

      // Dispatch event for subscribers.
      $user = $this->entityTypeManager->getStorage('user')->load($uid);
      if ($user) {
        $event = new WalletLinkedEvent($walletAddress, $uid, $user, $isNew);
        $this->eventDispatcher->dispatch($event, WalletAuthEvents::WALLET_LINKED);
      }

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
   * Create a new user from a wallet address.
   *
   * @param string $walletAddress
   *   The wallet address.
   *
   * @return \Drupal\user\UserInterface
   *   The created user.
   */
  public function createUserFromWallet(string $walletAddress): UserInterface {
    // ExternalAuth prefixes usernames with provider name, so we pass just
    // the wallet identifier (without 'wallet_' prefix) to avoid duplication.
    // Final username will be: wallet_auth_{wallet_hex}.
    $externalUsername = $this->generateUsername($walletAddress);
    $finalUsername = 'wallet_auth_' . $externalUsername;
    $email = $finalUsername . '@wallet.local';

    // Use External Auth to register the user.
    $account = $this->externalAuth->register($externalUsername, 'wallet_auth');
    $account->setEmail($email);
    $account->activate();
    $account->save();

    // Link wallet to user.
    $this->linkWalletToUser($walletAddress, (int) $account->id());

    $this->logger->info('Created new user @username from wallet @wallet', [
      '@username' => $finalUsername,
      '@wallet' => $walletAddress,
    ]);

    return $account;
  }

  /**
   * Generate a unique username from a wallet address.
   *
   * Note: This returns the base username (without provider prefix).
   * ExternalAuth will prefix with 'wallet_auth_' so final username is:
   * wallet_auth_{wallet_hex}
   *
   * @param string $walletAddress
   *   The wallet address.
   *
   * @return string
   *   A unique username (without provider prefix).
   */
  protected function generateUsername(string $walletAddress): string {
    // Use hash-based approach for privacy.
    $hash = substr(hash('sha256', $walletAddress), 0, 8);
    $baseUsername = 'wallet_' . $hash;
    $username = $baseUsername;
    $counter = 0;

    // Ensure username is unique (checking for final name with provider prefix).
    while ($this->usernameExists('wallet_auth_' . $username)) {
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
    $users = $this->entityTypeManager->getStorage('user')
      ->getQuery()
      ->condition('name', $username)
      ->range(0, 1)
      ->accessCheck(FALSE)
      ->execute();

    return !empty($users);
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
    // First try to load existing user.
    $existingUser = $this->loadUserByWalletAddress($walletAddress);

    if ($existingUser) {
      // Update last_used timestamp.
      /** @var \Drupal\wallet_auth\Entity\WalletAddressInterface[] $wallets */
      $wallets = $this->getWalletStorage()->loadByProperties([
        'wallet_address' => $walletAddress,
      ]);
      $wallet = reset($wallets);
      if ($wallet) {
        $wallet->setLastUsedTime($this->time->getRequestTime());
        $wallet->save();
      }

      $this->logger->info('Logging in existing user @uid for wallet @wallet', [
        '@uid' => $existingUser->id(),
        '@wallet' => $walletAddress,
      ]);

      return $existingUser;
    }

    // Create new user.
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
      /** @var \Drupal\wallet_auth\Entity\WalletAddressInterface[] $wallets */
      $wallets = $this->getWalletStorage()->loadByProperties([
        'uid' => $uid,
      ]);

      // Filter to active wallets only.
      $activeWallets = array_filter($wallets, function (WalletAddressInterface $wallet) {
        return $wallet->isActive();
      });

      return array_map(function (WalletAddressInterface $wallet) {
        return $wallet->getWalletAddress();
      }, $activeWallets);
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to get user wallets: @message', ['@message' => $e->getMessage()]);
      return [];
    }
  }

}
