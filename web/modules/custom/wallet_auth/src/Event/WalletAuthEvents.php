<?php

declare(strict_types=1);

namespace Drupal\wallet_auth\Event;

/**
 * Defines events for the wallet_auth module.
 */
final class WalletAuthEvents {

  /**
   * Name of the event dispatched when a wallet is linked to a user.
   *
   * This event allows modules to react when a wallet address is successfully
   * linked to a user account. The event listener receives a WalletLinkedEvent
   * instance containing the wallet address, user entity, and whether this is
   * a new link or an update to an existing link.
   *
   * @Event
   *
   * @see \Drupal\wallet_auth\Event\WalletLinkedEvent
   */
  public const WALLET_LINKED = 'wallet_auth.wallet.linked';

}
