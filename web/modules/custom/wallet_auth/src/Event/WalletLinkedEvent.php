<?php

declare(strict_types=1);

namespace Drupal\wallet_auth\Event;

use Drupal\user\UserInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched when a wallet is linked to a user.
 *
 * This event is dispatched after a wallet address is successfully linked to
 * a user account. Modules can subscribe to this event to perform additional
 * actions such as syncing data to other fields, sending notifications, or
 * updating external systems.
 */
class WalletLinkedEvent extends Event {

  /**
   * The wallet address.
   *
   * @var string
   */
  private string $walletAddress;

  /**
   * The user ID.
   *
   * @var int
   */
  private int $uid;

  /**
   * The user entity.
   *
   * @var \Drupal\user\UserInterface
   */
  private UserInterface $user;

  /**
   * Whether this is a new wallet link (vs. updating an existing one).
   *
   * @var bool
   */
  private bool $isNew;

  /**
   * Constructs a WalletLinkedEvent object.
   *
   * @param string $wallet_address
   *   The wallet address being linked.
   * @param int $uid
   *   The user ID.
   * @param \Drupal\user\UserInterface $user
   *   The user entity.
   * @param bool $is_new
   *   Whether this is a new wallet link.
   */
  public function __construct(string $wallet_address, int $uid, UserInterface $user, bool $is_new = TRUE) {
    $this->walletAddress = $wallet_address;
    $this->uid = $uid;
    $this->user = $user;
    $this->isNew = $is_new;
  }

  /**
   * Gets the wallet address.
   *
   * @return string
   *   The wallet address.
   */
  public function getWalletAddress(): string {
    return $this->walletAddress;
  }

  /**
   * Gets the user ID.
   *
   * @return int
   *   The user ID.
   */
  public function getUid(): int {
    return $this->uid;
  }

  /**
   * Gets the user entity.
   *
   * @return \Drupal\user\UserInterface
   *   The user entity.
   */
  public function getUser(): UserInterface {
    return $this->user;
  }

  /**
   * Checks if this is a new wallet link.
   *
   * @return bool
   *   TRUE if this is a new wallet link, FALSE otherwise.
   */
  public function isNew(): bool {
    return $this->isNew;
  }

}
