<?php

declare(strict_types=1);

namespace Drupal\wallet_auth;

use Drupal\user\UserInterface;

/**
 * Interface for managing wallet-to-user mapping and user creation.
 */
interface WalletUserManagerInterface {

  /**
   * Load a user by wallet address.
   *
   * @param string $walletAddress
   *   The wallet address.
   *
   * @return \Drupal\user\UserInterface|null
   *   The user entity if found, NULL otherwise.
   */
  public function loadUserByWalletAddress(string $walletAddress): ?UserInterface;

  /**
   * Link a wallet address to a user.
   *
   * @param string $walletAddress
   *   The wallet address.
   * @param int $uid
   *   The user ID.
   */
  public function linkWalletToUser(string $walletAddress, int $uid): void;

  /**
   * Create a new user from a wallet address.
   *
   * @param string $walletAddress
   *   The wallet address.
   *
   * @return \Drupal\user\UserInterface
   *   The created user.
   */
  public function createUserFromWallet(string $walletAddress): UserInterface;

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
  public function loginOrCreateUser(string $walletAddress): UserInterface;

  /**
   * Get all wallet addresses for a user.
   *
   * @param int $uid
   *   The user ID.
   *
   * @return array
   *   Array of wallet address strings.
   */
  public function getUserWallets(int $uid): array;

}
