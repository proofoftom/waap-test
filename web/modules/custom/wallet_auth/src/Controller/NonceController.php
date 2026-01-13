<?php

declare(strict_types=1);

namespace Drupal\wallet_auth\Controller;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\wallet_auth\Service\WalletVerification;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for wallet authentication nonce generation.
 */
class NonceController extends ControllerBase {

  /**
   * The wallet verification service.
   *
   * @var \Drupal\wallet_auth\Service\WalletVerification
   */
  protected WalletVerification $verification;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected Request $request;

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
   * Constructs a NonceController.
   *
   * @param \Drupal\wallet_auth\Service\WalletVerification $verification
   *   The wallet verification service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param \Drupal\Core\Flood\FloodInterface $flood
   *   The flood control service.
   * @param \Drupal\Core\Access\CsrfTokenGenerator $csrf_token
   *   The CSRF token generator.
   */
  public function __construct(
    WalletVerification $verification,
    ConfigFactoryInterface $config_factory,
    Request $request,
    FloodInterface $flood,
    CsrfTokenGenerator $csrf_token,
  ) {
    $this->verification = $verification;
    $this->configFactory = $config_factory;
    $this->request = $request;
    $this->flood = $flood;
    $this->csrfToken = $csrf_token;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('wallet_auth.verification'),
      $container->get('config.factory'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('flood'),
      $container->get('csrf_token')
    );
  }

  /**
   * Generate a nonce for wallet authentication.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with nonce and expires_in.
   */
  public function generateNonce(): JsonResponse {
    // Check rate limiting: 10 requests per 60 seconds per IP.
    $ip = $this->request->getClientIp();
    if (!$this->flood->isAllowed('wallet_auth.nonce', 10, 60, $ip)) {
      return new JsonResponse([
        'error' => 'Too many nonce requests. Please try again later.',
      ], 429);
    }

    $walletAddress = $this->request->query->get('wallet_address');

    if (empty($walletAddress) || !$this->verification->validateAddress($walletAddress)) {
      return new JsonResponse(['error' => 'Invalid wallet address'], 400);
    }

    try {
      $nonce = $this->verification->generateNonce();
      $this->verification->storeNonce($nonce, $walletAddress);

      // Register the flood event.
      $this->flood->register('wallet_auth.nonce', 60, $ip);

      // Read nonce lifetime from configuration.
      $lifetime = $this->configFactory->get('wallet_auth.settings')->get('nonce_lifetime');

      return new JsonResponse([
        'nonce' => $nonce,
        'expires_in' => $lifetime ?? 300,
        'csrf_token' => $this->csrfToken->get('wallet_auth'),
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse(['error' => 'Failed to generate nonce'], 500);
    }
  }

}
