<?php

declare(strict_types=1);

namespace Drupal\wallet_auth\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Config\ConfigFactoryInterface;
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
  protected ConfigFactoryInterface $configFactory;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected Request $request;

  /**
   * Constructs a NonceController.
   *
   * @param \Drupal\wallet_auth\Service\WalletVerification $verification
   *   The wallet verification service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   */
  public function __construct(
    WalletVerification $verification,
    ConfigFactoryInterface $config_factory,
    Request $request
  ) {
    $this->verification = $verification;
    $this->configFactory = $config_factory;
    $this->request = $request;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('wallet_auth.verification'),
      $container->get('config.factory'),
      $container->get('request_stack')->getCurrentRequest()
    );
  }

  /**
   * Generate a nonce for wallet authentication.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with nonce and expires_in.
   */
  public function generateNonce(): JsonResponse {
    $walletAddress = $this->request->query->get('wallet_address');

    if (empty($walletAddress) || !$this->verification->validateAddress($walletAddress)) {
      return new JsonResponse(['error' => 'Invalid wallet address'], 400);
    }

    try {
      $nonce = $this->verification->generateNonce();
      $this->verification->storeNonce($nonce, $walletAddress);

      // Read nonce lifetime from configuration.
      $lifetime = $this->configFactory->get('wallet_auth.settings')->get('nonce_lifetime');

      return new JsonResponse([
        'nonce' => $nonce,
        'expires_in' => $lifetime ?? 300,
      ]);
    }
    catch (\Exception $e) {
      return new JsonResponse(['error' => 'Failed to generate nonce'], 500);
    }
  }

}
