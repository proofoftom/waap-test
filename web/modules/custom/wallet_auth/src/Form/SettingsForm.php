<?php

declare(strict_types=1);

namespace Drupal\wallet_auth\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Routing\RequestContext;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure wallet authentication settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The request context.
   *
   * @var \Drupal\Core\Routing\RequestContext
   */
  protected $requestContext;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The file storage.
   *
   * @var \Drupal\file\FileStorageInterface
   */
  protected $fileStorage;

  /**
   * Constructs a SettingsForm.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   * @param \Drupal\Core\Routing\RequestContext $request_context
   *   The request context.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\file\FileStorageInterface $file_storage
   *   The file storage.
   */
  public function __construct(
    $config_factory,
    LoggerChannelFactoryInterface $logger_factory,
    RequestContext $request_context,
    FileSystemInterface $file_system,
    FileStorageInterface $file_storage,
  ) {
    parent::__construct($config_factory);
    $this->logger = $logger_factory->get('wallet_auth');
    $this->requestContext = $request_context;
    $this->fileSystem = $file_system;
    $this->fileStorage = $file_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('logger.factory'),
      $container->get('router.request_context'),
      $container->get('file_system'),
      $container->get('entity_type.manager')->getStorage('file'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['wallet_auth.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'wallet_auth_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('wallet_auth.settings');

    // Basic Settings (expanded by default).
    $form['basic_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Basic Settings'),
      '#open' => TRUE,
    ];

    $form['basic_settings']['network'] = [
      '#type' => 'select',
      '#title' => $this->t('Blockchain network'),
      '#description' => $this->t('Select the blockchain network to use for wallet authentication.'),
      '#options' => [
        'mainnet' => $this->t('Ethereum Mainnet'),
        'sepolia' => $this->t('Sepolia Testnet'),
        'polygon' => $this->t('Polygon'),
        'bsc' => $this->t('Binance Smart Chain'),
        'arbitrum' => $this->t('Arbitrum'),
        'optimism' => $this->t('Optimism'),
      ],
      '#default_value' => $config->get('network') ?? 'mainnet',
      '#required' => TRUE,
    ];

    $form['basic_settings']['nonce_lifetime'] = [
      '#type' => 'number',
      '#title' => $this->t('Authentication timeout'),
      '#description' => $this->t('How long the authentication challenge is valid in seconds. Default is 300 (5 minutes).'),
      '#default_value' => $config->get('nonce_lifetime') ?? 300,
      '#min' => 60,
      '#max' => 3600,
      '#required' => TRUE,
      '#field_suffix' => $this->t('seconds'),
    ];

    $form['basic_settings']['redirect_on_success'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Redirect path after login'),
      '#description' => $this->t('The internal Drupal path to redirect to after successful authentication (e.g., /user or /dashboard).'),
      '#default_value' => $config->get('redirect_on_success') ?? '/user',
      '#required' => TRUE,
      '#field_prefix' => $this->requestContext->getCompleteBaseUrl(),
    ];

    // Authentication Methods (expanded by default).
    $form['authentication_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Authentication Methods'),
      '#open' => TRUE,
    ];

    $form['authentication_settings']['authentication_methods'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Authentication methods'),
      '#description' => $this->t('Select which authentication methods to display.'),
      '#options' => [
        'email' => $this->t('Email'),
        'social' => $this->t('Social'),
        'wallet' => $this->t('Wallet'),
        'phone' => $this->t('Phone'),
      ],
      '#default_value' => $config->get('authentication_methods') ?? ['email', 'social'],
      '#required' => TRUE,
    ];

    $form['authentication_settings']['allowed_socials'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Allowed social providers'),
      '#description' => $this->t('Select which social providers to allow.'),
      '#options' => [
        'google' => $this->t('Google'),
        'twitter' => $this->t('Twitter/X'),
        'discord' => $this->t('Discord'),
        'bluesky' => $this->t('Bluesky'),
      ],
      '#default_value' => $config->get('allowed_socials') ?? ['google', 'twitter', 'discord', 'bluesky'],
      '#required' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="authentication_methods[social]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['authentication_settings']['walletConnectProjectId'] = [
      '#type' => 'textfield',
      '#title' => $this->t('WalletConnect Project ID'),
      '#description' => $this->t('Required when Wallet authentication is enabled. Get your project ID from <a href=":url">WalletConnect Cloud</a>.', [':url' => 'https://cloud.walletconnect.com']),
      '#default_value' => $config->get('walletConnectProjectId') ?? '',
      '#states' => [
        'required' => [
          ':input[name="authentication_methods[wallet]"]' => ['checked' => TRUE],
        ],
        'visible' => [
          ':input[name="authentication_methods[wallet]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Display & Branding (collapsed by default).
    $form['branding_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Display & Branding'),
      '#open' => FALSE,
    ];

    $form['branding_settings']['styles_darkMode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable dark mode'),
      '#description' => $this->t('Use dark mode for the authentication UI.'),
      '#default_value' => $config->get('styles.darkMode') ?? FALSE,
    ];

    $form['branding_settings']['showSecured'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show "Secured by human.tech" badge'),
      '#description' => $this->t('Display the secured by human.tech branding in the authentication UI.'),
      '#default_value' => $config->get('showSecured') ?? TRUE,
    ];

    $form['branding_settings']['project_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Project name'),
      '#description' => $this->t('Optional: Your project name to display in the authentication UI.'),
      '#default_value' => $config->get('project.name') ?? '',
      '#maxlength' => 255,
    ];

    $form['branding_settings']['project_logo'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Project logo'),
      '#description' => $this->t('Optional: Upload a logo image to display in the authentication UI. Recommended: Square image, PNG or JPG format, max 500KB.'),
      '#upload_location' => 'public://wallet_auth/logo',
      '#upload_validators' => [
        'file_validate_is_image' => [],
        'file_validate_extensions' => ['png jpg jpeg svg'],
        'file_validate_size' => [500 * 1024],
      ],
      '#default_value' => $config->get('project.logo_fid') ? [$config->get('project.logo_fid')] : NULL,
      '#extended' => TRUE,
    ];

    $form['branding_settings']['project_entryTitle'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Welcome message'),
      '#description' => $this->t('Optional: Custom welcome message to display (e.g., "Welcome to My App").'),
      '#default_value' => $config->get('project.entryTitle') ?? '',
      '#maxlength' => 255,
    ];

    $form['branding_settings']['button_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Sign-in button text'),
      '#description' => $this->t('Text to display on the sign-in button.'),
      '#default_value' => $config->get('button_text') ?? 'Sign In',
      '#maxlength' => 50,
      '#required' => TRUE,
    ];

    $form['branding_settings']['display_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Display style'),
      '#description' => $this->t('Choose how the sign-in element appears. "Link" matches navigation links. "Button" uses theme button styling.'),
      '#options' => [
        'link' => $this->t('Link'),
        'button' => $this->t('Button'),
      ],
      '#default_value' => $config->get('display_mode') ?? 'link',
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $authentication_methods = array_filter($form_state->getValue('authentication_methods'));

    // Validate walletConnectProjectId if wallet is enabled.
    if (in_array('wallet', $authentication_methods)) {
      $wallet_connect_id = $form_state->getValue('walletConnectProjectId');
      if (empty(trim($wallet_connect_id))) {
        $form_state->setErrorByName('authentication_settings][walletConnectProjectId', $this->t('WalletConnect Project ID is required when Wallet authentication is enabled.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Filter out unchecked values from checkboxes.
    $authentication_methods = array_filter($form_state->getValue('authentication_methods'));
    $allowed_socials = array_filter($form_state->getValue('allowed_socials'));

    // Handle file upload - convert to base64.
    $logo_base64 = '';
    $logo_fid = NULL;
    $file_upload = $form_state->getValue(['project_logo', 0]);

    if (!empty($file_upload['fids'])) {
      $fid = reset($file_upload['fids']);
      $file = $this->fileStorage->load($fid);

      if ($file) {
        // Mark file as permanent.
        $file->setPermanent();
        $file->save();

        // Convert to base64.
        $file_uri = $file->getFileUri();
        $file_path = $this->fileSystem->realpath($file_uri);
        $file_content = file_get_contents($file_path);
        $logo_base64 = base64_encode($file_content);
        $logo_fid = $fid;
      }
    }

    // Handle file removal - check if fid is in form values but empty.
    $old_fid = $this->config('wallet_auth.settings')->get('project.logo_fid');
    if (empty($logo_fid) && $old_fid) {
      // Check if the file was removed (no new fids).
      if (empty($file_upload['fids'])) {
        $old_file = $this->fileStorage->load($old_fid);
        if ($old_file) {
          $old_file->delete();
        }
      }
    }

    $this->config('wallet_auth.settings')
      ->set('network', $form_state->getValue('network'))
      ->set('nonce_lifetime', (int) $form_state->getValue('nonce_lifetime'))
      ->set('authentication_methods', array_values($authentication_methods))
      ->set('allowed_socials', array_values($allowed_socials))
      ->set('redirect_on_success', $form_state->getValue('redirect_on_success'))
      ->set('walletConnectProjectId', $form_state->getValue('walletConnectProjectId'))
      ->set('styles.darkMode', (bool) $form_state->getValue('styles_darkMode'))
      ->set('showSecured', (bool) $form_state->getValue('showSecured'))
      ->set('project.name', $form_state->getValue('project_name'))
      ->set('project.logo', $logo_base64)
      ->set('project.logo_fid', $logo_fid)
      ->set('project.entryTitle', $form_state->getValue('project_entryTitle'))
      ->set('button_text', $form_state->getValue('button_text'))
      ->set('display_mode', $form_state->getValue('display_mode'))
      ->save();

    $this->logger->info('Wallet authentication settings updated.');
    parent::submitForm($form, $form_state);
  }

}
