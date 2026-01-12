<?php

declare(strict_types=1);

namespace Drupal\wallet_auth\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\wallet_auth\Service\WalletVerification;
use Drupal\wallet_auth\Service\WalletUserManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for wallet authentication REST endpoint.
 */
class AuthenticateController extends ControllerBase {

  /**
   * The wallet verification service.
   *
   * @var \Drupal\wallet_auth\Service\WalletVerification
   */
  protected $verification;

  /**
   * The wallet user manager service.
   *
   * @var \Drupal\wallet_auth\Service\WalletUserManager
   */
  protected $userManager;

  /**
   * Constructs an AuthenticateController.
   *
   * @param \Drupal\wallet_auth\Service\WalletVerification $verification
   *   The wallet verification service.
   * @param \Drupal\wallet_auth\Service\WalletUserManager $user_manager
   *   The wallet user manager service.
   */
  public function __construct(
    WalletVerification $verification,
    WalletUserManager $user_manager,
  ) {
    $this->verification = $verification;
    $this->userManager = $user_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('wallet_auth.verification'),
      $container->get('wallet_auth.user_manager'),
    );
  }

  /**
   * Authenticate a user using wallet signature.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with authentication result.
   */
  public function authenticate(Request $request): JsonResponse {
    try {
      // 1. Parse request
      $data = json_decode($request->getContent(), TRUE);
      $walletAddress = $data['wallet_address'] ?? '';
      $signature = $data['signature'] ?? '';
      $message = $data['message'] ?? '';
      $nonce = $data['nonce'] ?? '';

      // 2. Validate address format
      if (!$this->verification->validateAddress($walletAddress)) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Invalid wallet address',
        ], 400);
      }

      // 3. Verify signature and SIWE message (includes nonce validation)
      if (!$this->verification->verifySignature($message, $signature, $walletAddress)) {
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Invalid signature',
        ], 401);
      }

      // 4. Delete nonce after successful verification
      $this->verification->deleteNonce($nonce);

      // 5. Load or create user
      $user = $this->userManager->loginOrCreateUser($walletAddress);

      // 6. Log user in
      user_login_finalize($user);

      // 7. Return success
      return new JsonResponse([
        'success' => TRUE,
        'uid' => $user->id(),
        'username' => $user->getAccountName(),
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Authentication failed',
      ], 500);
    }
  }

}
