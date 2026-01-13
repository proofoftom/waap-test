<?php

namespace Drupal\wallet_auth\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Custom access check for wallet authentication routes.
 */
class WalletAuthAccessCheck implements AccessInterface {

  /**
   * Checks access for wallet authentication routes.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account to check access for.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account) {
    // Anonymous users can always attempt wallet auth (they need to log in!)
    if ($account->isAnonymous()) {
      return AccessResult::allowed()->addCacheContexts(['user.roles:anonymous']);
    }
    // Authenticated users need permission to link additional wallets
    return AccessResult::allowedIfHasPermission($account, 'authenticate with wallet');
  }

}
