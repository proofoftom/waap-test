<?php

declare(strict_types=1);

namespace Drupal\wallet_auth\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining wallet address entities.
 *
 * @ingroup wallet_auth
 */
interface WalletAddressInterface extends ContentEntityInterface, EntityOwnerInterface {

  /**
   * Gets the wallet address.
   *
   * @return string
   *   The wallet address.
   */
  public function getWalletAddress(): string;

  /**
   * Sets the wallet address.
   *
   * @param string $address
   *   The wallet address.
   *
   * @return \Drupal\wallet_auth\Entity\WalletAddressInterface
   *   The called wallet address entity.
   */
  public function setWalletAddress(string $address): WalletAddressInterface;

  /**
   * Gets the wallet address creation timestamp.
   *
   * @return int
   *   The creation timestamp.
   */
  public function getCreatedTime(): int;

  /**
   * Sets the wallet address creation timestamp.
   *
   * @param int $timestamp
   *   The creation timestamp.
   *
   * @return \Drupal\wallet_auth\Entity\WalletAddressInterface
   *   The called wallet address entity.
   */
  public function setCreatedTime(int $timestamp): WalletAddressInterface;

  /**
   * Gets the wallet address last used timestamp.
   *
   * @return int
   *   The last used timestamp.
   */
  public function getLastUsedTime(): int;

  /**
   * Sets the wallet address last used timestamp.
   *
   * @param int $timestamp
   *   The last used timestamp.
   *
   * @return \Drupal\wallet_auth\Entity\WalletAddressInterface
   *   The called wallet address entity.
   */
  public function setLastUsedTime(int $timestamp): WalletAddressInterface;

  /**
   * Gets the wallet address status.
   *
   * @return bool
   *   TRUE if the wallet address is active, FALSE otherwise.
   */
  public function isActive(): bool;

  /**
   * Sets the wallet address status.
   *
   * @param bool $status
   *   TRUE to activate the wallet address, FALSE to deactivate.
   *
   * @return \Drupal\wallet_auth\Entity\WalletAddressInterface
   *   The called wallet address entity.
   */
  public function setActive(bool $status): WalletAddressInterface;

}
