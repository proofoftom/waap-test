<?php

declare(strict_types=1);

namespace Drupal\wallet_auth\Controller;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
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
  protected WalletVerification $verification;

  /**
   * The wallet user manager service.
   *
   * @var \Drupal\wallet_auth\Service\WalletUserManager
   */
  protected WalletUserManager $userManager;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The flood control service.
   *
   * @var \Drupal\Core\Flood\FloodInterface
   */
  protected FloodInterface $flood;

  /**
   * The CSRF token generator.
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   */
  protected CsrfTokenGenerator $csrfToken;

  /**
   * Constructs an AuthenticateController.
   *
   * @param \Drupal\wallet_auth\Service\WalletVerification $verification
   *   The wallet verification service.
   * @param \Drupal\wallet_auth\Service\WalletUserManager $user_manager
   *   The wallet user manager service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   * @param \Drupal\Core\Flood\FloodInterface $flood
   *   The flood control service.
   * @param \Drupal\Core\Access\CsrfTokenGenerator $csrf_token
   *   The CSRF token generator.
   */
  public function __construct(
    WalletVerification $verification,
    WalletUserManager $user_manager,
    LoggerChannelFactoryInterface $logger_factory,
    FloodInterface $flood,
    CsrfTokenGenerator $csrf_token,
  ) {
    $this->verification = $verification;
    $this->userManager = $user_manager;
    $this->logger = $logger_factory->get('wallet_auth');
    $this->flood = $flood;
    $this->csrfToken = $csrf_token;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('wallet_auth.verification'),
      $container->get('wallet_auth.user_manager'),
      $container->get('logger.factory'),
      $container->get('flood'),
      $container->get('csrf_token')
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
    // Check rate limiting: 5 requests per 60 seconds per IP.
    $ip = $request->getClientIp();
    if (!$this->flood->isAllowed('wallet_auth.authenticate', 5, 60, $ip)) {
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Too many authentication attempts. Please try again later.',
      ], 429);
    }

    try {
      // 1. Parse request
      $data = json_decode($request->getContent(), TRUE);
      $walletAddress = $data['wallet_address'] ?? '';
      $signature = $data['signature'] ?? '';
      $message = $data['message'] ?? '';
      $nonce = $data['nonce'] ?? '';
      $csrf = $data['csrf_token'] ?? '';

      // 2. Validate CSRF token
      if (!$this->csrfToken->validate($csrf, 'wallet_auth')) {
        $this->logger->warning('Authentication attempt with invalid CSRF token from IP: @ip', [
          '@ip' => $ip,
        ]);
        // Register flood event on CSRF validation failure.
        $this->flood->register('wallet_auth.authenticate', 60, $ip);
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Invalid CSRF token',
        ], 403);
      }

      // 3. Validate address format
      if (!$this->verification->validateAddress($walletAddress)) {
        $this->logger->warning('Authentication attempt with invalid wallet address format: @address', [
          '@address' => $walletAddress,
        ]);
        // Register flood event even on validation failure.
        $this->flood->register('wallet_auth.authenticate', 60, $ip);
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Invalid wallet address',
        ], 400);
      }

      // 4. Verify signature and SIWE message (includes nonce validation)
      if (!$this->verification->verifySignature($message, $signature, $walletAddress)) {
        $this->logger->warning('Authentication failed for wallet @wallet: invalid signature', [
          '@wallet' => $walletAddress,
        ]);
        // Register flood event on failed authentication.
        $this->flood->register('wallet_auth.authenticate', 60, $ip);
        return new JsonResponse([
          'success' => FALSE,
          'error' => 'Invalid signature',
        ], 401);
      }

      // 5. Delete nonce after successful verification
      $this->verification->deleteNonce($nonce);

      // 6. Load or create user
      $user = $this->userManager->loginOrCreateUser($walletAddress);

      // 7. Log user in
      user_login_finalize($user);

      $this->logger->info('User authenticated successfully via wallet @wallet', [
        '@wallet' => $walletAddress,
        'uid' => $user->id(),
      ]);

      // 8. Clear flood events on successful authentication.
      $this->flood->clear('wallet_auth.authenticate', $ip);

      // 9. Return success
      return new JsonResponse([
        'success' => TRUE,
        'uid' => $user->id(),
        'username' => $user->getAccountName(),
      ]);
    }
    catch (\Exception $e) {
      $this->logger->error('Authentication error: @message', [
        '@message' => $e->getMessage(),
      ]);
      // Register flood event on exception.
      $this->flood->register('wallet_auth.authenticate', 60, $ip);
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Authentication failed',
        'error_code' => 'INTERNAL_ERROR',
      ], 500);
    }
  }

}
