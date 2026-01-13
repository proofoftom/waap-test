---
name: "drupal-module-polish"
description: "Add configuration management, admin interfaces, code quality tools, and documentation to Drupal custom modules. Use when moving from prototype to production-ready module."
---

# Drupal Module Polish Skill

This skill helps you add production-ready features to Drupal custom modules, following established patterns from the wallet_auth module integration phase.

## When to Use This Skill

Use this skill when you need to:
- Add admin settings forms to Drupal modules
- Implement configuration schema and defaults
- Enforce Drupal coding standards (PHPCS, PHPStan)
- Create comprehensive module documentation
- Integrate frontend with backend via drupalSettings

**Trigger phrases:**
- "add config to drupal module"
- "create admin form"
- "polish drupal module"
- "add drupalsettings"
- "run phpcs"
- "module documentation"

## Configuration Management Pattern

### 1. Create Configuration Schema

**File:** `config/schema/MODULE_NAME.schema.yml`

```yaml
MODULE_NAME.settings:
  type: config_object
  label: 'Module Name Settings'
  mapping:
    # String setting
    network:
      type: string
      label: 'Blockchain network'
      nullable: true

    # Boolean setting
    enable_auto_connect:
      type: boolean
      label: 'Enable auto-connect'

    # Integer setting
    nonce_lifetime:
      type: integer
      label: 'Nonce lifetime (seconds)'

    # Mapping/nested setting
    api_credentials:
      type: mapping
      label: 'API Credentials'
      mapping:
        endpoint:
          type: uri
          label: 'API Endpoint'
        key:
          type: string
          label: 'API Key'
```

### 2. Create Default Configuration

**File:** `config/install/MODULE_NAME.settings.yml`

```yaml
# Default configuration values
network: 'mainnet'
enable_auto_connect: true
nonce_lifetime: 300
api_credentials:
  endpoint: 'https://api.example.com'
  key: ''
```

### 3. Create Settings Form

**File:** `src/Form/SettingsForm.php`

```php
<?php

namespace Drupal\MODULE_NAME\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure MODULE_NAME settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['MODULE_NAME.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'MODULE_NAME_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('MODULE_NAME.settings');

    // Text field example
    $form['network'] = [
      '#type' => 'select',
      '#title' => $this->t('Blockchain network'),
      '#description' => $this->t('Select the blockchain network to use.'),
      '#options' => [
        'mainnet' => $this->t('Mainnet'),
        'sepolia' => $this->t('Sepolia Testnet'),
        'polygon' => $this->t('Polygon'),
      ],
      '#default_value' => $config->get('network'),
      '#required' => TRUE,
    ];

    // Checkbox example
    $form['enable_auto_connect'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable auto-connect'),
      '#description' => $this->t('Automatically connect wallet on page load.'),
      '#default_value' => $config->get('enable_auto_connect'),
    ];

    // Number field example
    $form['nonce_lifetime'] = [
      '#type' => 'number',
      '#title' => $this->t('Nonce lifetime'),
      '#description' => $this->t('How long nonces remain valid (in seconds).'),
      '#default_value' => $config->get('nonce_lifetime'),
      '#min' => 60,
      '#max' => 3600,
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('MODULE_NAME.settings')
      ->set('network', $form_state->getValue('network'))
      ->set('enable_auto_connect', $form_state->getValue('enable_auto_connect'))
      ->set('nonce_lifetime', $form_state->getValue('nonce_lifetime'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
```

### 4. Register Admin Route

**File:** `MODULE_NAME.routing.yml`

```yaml
MODULE_NAME.settings:
  path: '/admin/config/people/MODULE_NAME'
  defaults:
    _form: '\Drupal\MODULE_NAME\Form\SettingsForm'
    _title: 'Module Name Settings'
  requirements:
    _permission: 'administer MODULE_NAME configuration'
```

### 5. Add Menu Link

**File:** `MODULE_NAME.links.menu.yml`

```yaml
MODULE_NAME.settings:
  title: 'Module Name'
  description: 'Configure Module Name settings.'
  route_name: MODULE_NAME.settings
  parent: user.admin_index  # Places under People section
  weight: 10
```

**Common parent sections:**
- `user.admin_index` - People section
- `system.admin_config_system` - System section
- `system.admin_config_content` - Content section
- `system.admin_config_services` - Services section

### 6. Add Permission

**File:** `MODULE_NAME.permissions.yml`

```yaml
administer MODULE_NAME configuration:
  title: 'Administer Module Name settings'
  description: 'Allow users to configure Module Name settings.'
  restrict access: true
```

## Code Quality Pattern

### PHPCS (PHP Code Sniffer)

**Run PHPCS on module:**
```bash
vendor/bin/phpcs --standard=Drupal,DrupalPractice web/modules/custom/MODULE_NAME/
```

**Run PHPCS on specific file:**
```bash
vendor/bin/phpcs --standard=Drupal,DrupalPractice web/modules/custom/MODULE_NAME/src/Controller/ExampleController.php
```

**Auto-fix violations:**
```bash
vendor/bin/phpcbf --standard=Drupal,DrupalPractice web/modules/custom/MODULE_NAME/
```

### Common PHPCS Violations & Fixes

1. **Line length exceeds 80 characters**
   - Fix: Break long lines, use string concatenation
   - Or: Configure to allow 120+ characters

2. **Missing namespace doc comment**
   - Add file-level doc comment after `namespace`

3. **Unused use statement**
   - Remove unused `use` statements

4. **Missing array type hint**
   - Change `array $form` to `array &$form`

### PHPStan (Static Analysis)

**Run PHPStan:**
```bash
vendor/bin/phpstan analyse web/modules/custom/MODULE_NAME/ --level=1
```

**With configuration file:**
```bash
vendor/bin/phpstan analyse -c phpstan.neon web/modules/custom/MODULE_NAME/
```

**Create phpstan.neon:**
```neon
parameters:
  level: 1
  paths:
    - web/modules/custom/MODULE_NAME/src
  bootstrapFiles:
    - web/core/tests/bootstrap.php
  drupal:
    drupalRoot: web
```

### Common PHPStan Errors & Fixes

1. **Undefined variable: $config**
   - Add property declaration: `protected ConfigFactoryInterface $configFactory;`

2. **Property type not specified**
   - Add type hints: `private readonly LoggerInterface $logger;`

3. **Unsafe usage of new static()**
   - Use dependency injection instead

## Frontend Integration Pattern

### 1. Pass Config to Frontend via drupalSettings

**In hook_preprocess_page() or in controller:**

```php
// In a preprocess hook
function MODULE_NAME_preprocess_page(&$variables) {
  $config = \Drupal::config('MODULE_NAME.settings');

  $variables['#attached']['drupalSettings']['MODULE_NAME'] = [
    'network' => $config->get('network'),
    'enableAutoConnect' => $config->get('enable_auto_connect'),
    'nonceLifetime' => $config->get('nonce_lifetime'),
  ];
}
```

**Or in a controller (better pattern):**

```php
public function build() {
  $config = $this->config('MODULE_NAME.settings');

  return [
    '#theme' => 'MODULE_NAME_block',
    '#attached' => [
      'library' => 'MODULE_NAME/wallet',
      'drupalSettings' => [
        'MODULE_NAME' => [
          'network' => $config->get('network'),
          'enableAutoConnect' => $config->get('enable_auto_connect'),
          'nonceLifetime' => $config->get('nonce_lifetime'),
        ],
      ],
    ],
  ];
}
```

### 2. Access in JavaScript

```javascript
(function (Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.MODULE_NAME = {
    attach: function (context, settings) {
      const config = drupalSettings.MODULE_NAME;

      console.log('Network:', config.network);
      console.log('Auto-connect:', config.enableAutoConnect);
      console.log('Nonce lifetime:', config.nonceLifetime);

      // Initialize wallet with config
      if (config.enableAutoConnect) {
        // Auto-connect logic
      }
    }
  };
})(Drupal, drupalSettings);
```

### 3. Define Library

**File:** `MODULE_NAME.libraries.yml`

```yaml
wallet:
  version: 1.0
  js:
    js/wallet.js: {}
  css:
    theme:
      css/wallet.css: {}
  dependencies:
    - core/drupal
    - core/drupalSettings
    - core/jquery
```

## Documentation Template

### README.md Structure

```markdown
# Module Name

Brief one-line description of what the module does.

## Requirements

- Drupal 10+
- PHP 8.1+
- [Any other dependencies]

## Installation

1. Copy the module to `web/modules/custom/MODULE_NAME`
2. Enable the module: `drush en MODULE_NAME`
3. Or enable via admin interface: Extend > List > Install "Module Name"
4. Configure at `/admin/config/people/MODULE_NAME`

## Configuration

Navigate to **Administration > Configuration > People > Module Name** to configure:

- **Network**: Select the blockchain network (mainnet, sepolia, polygon)
- **Enable auto-connect**: Automatically connect wallet on page load
- **Nonce lifetime**: Set how long nonces remain valid (60-3600 seconds)

## Usage

### For Site Visitors

1. Navigate to a page with the wallet block
2. Click "Connect Wallet"
3. Sign the authentication message in your wallet
4. You are now logged in

### For Developers

#### API Endpoints

**POST /MODULE_NAME/authenticate**
Authenticates a user via wallet signature.

Request:
```json
{
  "message": "Domain wants you to sign in...",
  "signature": "0x...",
  "address": "0x..."
}
```

Response:
```json
{
  "token": "jwt_token_here",
  "user": {
    "uid": 123,
    "name": "user_0x1234..."
  }
}
```

#### Frontend Integration

Add the wallet block to any region via:
1. Structure > Block layout
2. Select the desired theme/region
3. Place "Wallet Connect" block

## Troubleshooting

### "Nonce expired or invalid"

**Cause**: The nonce has exceeded its lifetime or was already used.

**Solution**:
1. Ensure your system clock is synchronized
2. Increase the nonce lifetime in module settings
3. Clear your browser cache and try again

### "Signature verification failed"

**Cause**: The signature doesn't match the message or address.

**Solution**:
1. Ensure you're signing the exact message displayed
2. Check that you're using the correct wallet address
3. Try refreshing the page to get a new nonce

### "Wallet not connected"

**Cause**: No wallet extension is detected or connected.

**Solution**:
1. Install a Web3 wallet extension (MetaMask, WalletConnect, etc.)
2. Ensure the wallet is unlocked
3. Check that your wallet is on the correct network

### Configuration not saving

**Cause**: Missing permissions or cache issues.

**Solution**:
1. Ensure you have "administer MODULE_NAME configuration" permission
2. Run `drush cr` to clear caches
3. Check watchdog logs for detailed errors

## Development Roadmap

- [ ] Phase 1: Basic authentication
- [x] Phase 2: REST API
- [x] Phase 3: Frontend integration
- [x] Phase 4: User management
- [x] Phase 5: Configuration & polish
- [ ] Phase 6: Testing & validation

## Credits

Developed by [Your Name/Organization].

## License

GPL-2.0+
```

## Dependency Injection Pattern

### Inject Configuration Factory

```php
<?php

namespace Drupal\MODULE_NAME\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Psr\Log\LoggerInterface;

class MyService {

  protected $configFactory;
  protected $logger;

  /**
   * Constructs a new MyService object.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerInterface $logger) {
    $this->configFactory = $config_factory;
    $this->logger = $logger;
  }

  /**
   * Gets configuration value.
   */
  public function getSetting($key) {
    return $this->configFactory->get('MODULE_NAME.settings')->get($key);
  }

}
```

### Define Service

**File:** `MODULE_NAME.services.yml`

```yaml
services:
  MODULE_NAME.my_service:
    class: Drupal\MODULE_NAME\Service\MyService
    arguments:
      - '@config.factory'
      - '@logger.factory'
```

### Use in Controller/Form

```php
/**
 * {@inheritdoc}
 */
public static function create(ContainerInterface $container) {
  return new static(
    $container->get('MODULE_NAME.my_service')
  );
}

public function __construct(MyService $my_service) {
  $this->myService = $my_service;
}
```

## Logging Pattern

```php
// Debug: Detailed information for debugging
$this->logger->debug('Nonce generated: @nonce', ['@nonce' => $nonce]);

// Info: Interesting events (login, config changes)
$this->logger->info('User @uid authenticated from wallet @address', [
  '@uid' => $account->id(),
  '@address' => $address,
]);

// Notice: Normal but significant events
$this->logger->notice('Configuration updated by @user', [
  '@user' => $this->currentUser()->getAccountName(),
]);

// Warning: Exception occurrences that aren't errors
$this->logger->warning('Nonce validation failed for address @address', [
  '@address' => $address,
]);

// Error: Runtime errors that don't require immediate action
$this->logger->error('Failed to retrieve account info: @message', [
  '@message' => $e->getMessage(),
]);

// Critical: Critical conditions
$this->logger->critical('Database connection lost: @error', [
  '@error' => $e->getMessage(),
]);
```

## Verification Checklist

After completing module polish:

### Configuration
- [ ] Schema file created with proper types
- [ ] Default config file created
- [ ] Settings form extends ConfigFormBase
- [ ] All form fields use `$this->t()` for labels
- [ ] Route registered at `/admin/config/...`
- [ ] Menu link appears in correct section
- [ ] Permission defined for admin access
- [ ] Configuration saves and persists

### Code Quality
- [ ] PHPCS reports 0 errors
- [ ] PHPStan reports 0 errors
- [ ] No static `\Drupal::` calls (use DI)
- [ ] All strings use `$this->t()`
- [ ] Proper namespace doc comments
- [ ] Type hints on all properties/methods
- [ ] PHPDoc comments on public methods

### Frontend Integration
- [ ] drupalSettings passed to frontend
- [ ] Library defined in .libraries.yml
- [ ] JavaScript wrapped in Drupal.behaviors
- [ ] Config accessible in JS via drupalSettings

### Documentation
- [ ] README.md exists
- [ ] Installation instructions provided
- [ ] Configuration documented
- [ ] Usage examples included
- [ ] Troubleshooting section with common issues
- [ ] API endpoints documented (if applicable)

### Testing
- [ ] Settings form accessible
- [ ] Configuration saves correctly
- [ ] drupalSettings accessible in browser console
- [ ] Caches cleared (`drush cr`)
- [ ] Module enabled/disables cleanly
