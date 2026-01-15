<?php

declare(strict_types=1);

namespace Drupal\wallet_auth\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\Cache;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a wallet login button block.
 *
 * @Block(
 *   id = "wallet_login_block",
 *   admin_label = @Translation("Wallet Login Button"),
 *   category = @Translation("User")
 * )
 */
class WalletLoginBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $currentUser;

  /**
   * Constructs a WalletLoginBlock.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ConfigFactoryInterface $config_factory,
    AccountInterface $current_user,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];

    // Only show for anonymous users.
    if ($this->currentUser->isAuthenticated()) {
      return $build;
    }

    // Read configuration.
    $config = $this->configFactory->get('wallet_auth.settings');
    $network = $config->get('network') ?? 'mainnet';

    // Build WaaP config object for the SDK.
    $waapConfig = [
      'allowedSocials' => $config->get('allowed_socials') ?? ['google', 'twitter', 'discord', 'bluesky'],
      'authenticationMethods' => $config->get('authentication_methods') ?? ['email', 'social'],
    ];

    // Add styles if configured.
    $darkMode = $config->get('styles.darkMode');
    if ($darkMode !== NULL) {
      $waapConfig['styles'] = ['darkMode' => $darkMode];
    }

    // Add showSecured if explicitly set to false.
    if ($config->get('showSecured') === FALSE) {
      $waapConfig['showSecured'] = FALSE;
    }

    $build['#theme'] = 'wallet_login_button';
    $build['#attached'] = [
      'library' => [
        'wallet_auth/wallet_auth_ui',
      ],
      'drupalSettings' => [
        'walletAuth' => [
          'apiEndpoint' => '/wallet-auth',
          'network' => $network,
          'chainId' => $this->getChainId($network),
          'authenticationMethods' => $config->get('authentication_methods') ?? ['email', 'social'],
          'allowedSocials' => $config->get('allowed_socials') ?? ['google', 'twitter', 'discord', 'bluesky'],
          'redirectOnSuccess' => $config->get('redirect_on_success') ?? '/user',
          // WaaP SDK config.
          'waapConfig' => $waapConfig,
          'walletConnectProjectId' => $config->get('walletConnectProjectId') ?? '',
          // Project branding.
          'projectName' => $config->get('project.name') ?? '',
          'projectLogo' => $config->get('project.logo') ?? '',
          'projectEntryTitle' => $config->get('project.entryTitle') ?? '',
          // Button text and display mode.
          'buttonText' => $config->get('button_text') ?? 'Sign In',
          'displayMode' => $config->get('display_mode') ?? 'link',
        ],
      ],
    ];
    $build['#cache'] = [
      'tags' => ['config:wallet_auth.settings'],
      'contexts' => ['user.roles:anonymous'],
      'max-age' => Cache::PERMANENT,
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function blockAccess(AccountInterface $account) {
    // Only show for anonymous users. Cache per user role to ensure proper
    // visibility after login/logout.
    return AccessResult::allowedIf($account->isAnonymous())
      ->addCacheContexts(['user.roles:anonymous']);
  }

  /**
   * Get chain ID for a network name.
   *
   * @param string $network
   *   The network name (e.g., 'mainnet', 'sepolia').
   *
   * @return int
   *   The chain ID for the network.
   */
  protected function getChainId(string $network): int {
    $chainIds = [
      'mainnet' => 1,
      'sepolia' => 11155111,
      'polygon' => 137,
      'bsc' => 56,
      'arbitrum' => 42161,
      'optimism' => 10,
    ];

    return $chainIds[$network] ?? 1;
  }

}
