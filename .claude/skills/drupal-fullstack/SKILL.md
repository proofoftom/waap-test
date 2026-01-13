---
name: drupal-fullstack
description: Build full-stack Drupal modules with backend services and frontend JavaScript. Covers services, REST APIs, database schema, Vite builds, Drupal behaviors, and block plugins. Use when creating Drupal 10 modules that need both PHP services and JavaScript integration.
---

# Drupal Full-Stack Module Development

Complete technical patterns for building Drupal 10 modules with both backend services and frontend JavaScript integration.

## When to Use This Skill

Use this skill when you need to:
- Create a Drupal module with REST APIs
- Build custom services with dependency injection
- Add JavaScript with build pipeline (Vite/Webpack)
- Integrate frontend and backend via drupalSettings
- Create custom database tables and queries
- Implement block plugins with templates
- Handle external authentication/user management

**Trigger phrases:**
- "build drupal module"
- "create drupal module with frontend"
- "fullstack drupal development"
- "drupal rest api and frontend"
- "drupal module with javascript"

## Part 1: Backend Services Pattern

### Service Class Structure

**File:** `src/Service/YourService.php`

```php
<?php

declare(strict_types=1);

namespace Drupal\your_module\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Psr\Log\LoggerInterface;

/**
 * Your service description.
 *
 * @todo Add detailed service documentation.
 */
class YourService {

  /**
   * The database connection.
   */
  protected readonly Connection $database;

  /**
   * The configuration factory.
   */
  protected readonly ConfigFactoryInterface $configFactory;

  /**
   * The logger.
   */
  protected readonly LoggerInterface $logger;

  /**
   * The current user.
   */
  protected readonly AccountProxyInterface $currentUser;

  /**
   * Constructs a new YourService object.
   */
  public function __construct(
    Connection $database,
    ConfigFactoryInterface $config_factory,
    LoggerChannelFactoryInterface $logger_factory,
    AccountProxyInterface $current_user
  ) {
    $this->database = $database;
    $this->configFactory = $config_factory;
    $this->logger = $logger_factory->get('your_module');
    $this->currentUser = $current_user;
  }

  /**
   * Example method: Do something useful.
   *
   * @param string $input
   *   The input parameter.
   *
   * @return array
   *   The result.
   */
  public function doSomething(string $input): array {
    $this->logger->info('Processing input: @input', ['@input' => $input]);

    // Your logic here
    $result = ['processed' => TRUE, 'data' => $input];

    return $result;
  }

}
```

### Service Registration

**File:** `your_module.services.yml`

```yaml
services:
  your_module.your_service:
    class: Drupal\your_module\Service\YourService
    arguments:
      - '@database'
      - '@config.factory'
      - '@logger.factory'
      - '@current_user'
```

### Using Services in Controllers

```php
<?php

namespace Drupal\your_module\Controller;

use Drupal\your_module\Service\YourService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;

/**
 * Your controller.
 */
class YourController implements ContainerInjectionInterface {

  /**
   * The service.
   */
  protected readonly YourService $yourService;

  /**
   * Constructs a new YourController.
   */
  public function __construct(YourService $your_service) {
    $this->yourService = $your_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('your_module.your_service')
    );
  }

}
```

## Part 2: REST API Pattern

### REST Controller

**File:** `src/Controller/YourApiController.php`

```php
<?php

declare(strict_types=1);

namespace Drupal\your_module\Controller;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Your API controller.
 */
class YourApiController implements ContainerInjectionInterface {

  /**
   * The logger.
   */
  protected readonly LoggerInterface $logger;

  /**
   * Constructs a new YourApiController.
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory) {
    $this->logger = $logger_factory->get('your_module');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('logger.factory')
    );
  }

  /**
   * Handle POST request.
   */
  public function post(Request $request): JsonResponse {
    try {
      // Decode JSON payload
      $payload = json_decode($request->getContent(), TRUE);
      if (json_last_error() !== JSON_ERROR_NONE) {
        throw new \InvalidArgumentException('Invalid JSON payload');
      }

      // Validate required fields
      $required = ['field1', 'field2'];
      foreach ($required as $field) {
        if (empty($payload[$field])) {
          return new JsonResponse([
            'success' => FALSE,
            'error' => "Missing required field: {$field}",
          ], 400);
        }
      }

      // Process the request
      $result = $this->processRequest($payload);

      $this->logger->info('Request processed successfully');

      return new JsonResponse([
        'success' => TRUE,
        'data' => $result,
      ], 200);

    }
    catch (\Exception $e) {
      $this->logger->error('API error: @message', ['@message' => $e->getMessage()]);

      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Internal server error',
      ], 500);
    }
  }

  /**
   * Process the request payload.
   */
  protected function processRequest(array $payload): array {
    // Your processing logic
    return ['processed' => TRUE];
  }

}
```

### Route Registration

**File:** `your_module.routing.yml`

```yaml
your_module.api.endpoint:
  path: '/your-module/api/endpoint'
  methods: [POST]
  defaults:
    _controller: '\Drupal\your_module\Controller\YourApiController::post'
    _title: 'Your API Endpoint'
  requirements:
    _permission: 'access content'
    _csrf_request_header_token: TRUE
```

### Permission Definition

**File:** `your_module.permissions.yml`

```yaml
use your module api:
  title: 'Use Your Module API'
  description: 'Allow users to access your module API endpoints.'
```

## Part 3: Database Layer Pattern

### Schema Definition

**File:** `your_module.install`

```php
<?php

/**
 * @file
 * Install, update and uninstall functions for your_module.
 */

/**
 * Implements hook_schema().
 */
function your_module_schema(): array {
  $schema['your_module_table'] = [
    'description' => 'Stores your module data.',
    'fields' => [
      'id' => [
        'type' => 'serial',
        'not null' => TRUE,
        'description' => 'Primary Key: Unique record ID.',
      ],
      'external_id' => [
        'type' => 'varchar',
        'length' => 255,
        'not null' => TRUE,
        'description' => 'External identifier.',
      ],
      'uid' => [
        'type' => 'int',
        'unsigned' => TRUE,
        'not null' => TRUE,
        'description' => 'The {users}.uid associated with this record.',
      ],
      'data' => [
        'type' => 'text',
        'size' => 'big',
        'not null' => FALSE,
        'description' => 'Serialized data.',
      ],
      'status' => [
        'type' => 'int',
        'size' => 'tiny',
        'unsigned' => TRUE,
        'not null' => TRUE,
        'default' => 1,
        'description' => 'Status flag (1 = active, 0 = inactive).',
      ],
      'created' => [
        'type' => 'int',
        'unsigned' => TRUE,
        'not null' => TRUE,
        'default' => 0,
        'description' => 'Unix timestamp of when the record was created.',
      ],
      'changed' => [
        'type' => 'int',
        'unsigned' => TRUE,
        'not null' => TRUE,
        'default' => 0,
        'description' => 'Unix timestamp of when the record was last changed.',
      ],
    ],
    'primary key' => ['id'],
    'unique keys' => [
      'external_id' => ['external_id'],
    ],
    'indexes' => [
      'uid' => ['uid'],
      'status' => ['status'],
      'created' => ['created'],
    ],
    'foreign keys' => [
      'uid' => [
        'table' => 'users',
        'columns' => ['uid' => 'uid'],
      ],
    ],
  ];

  return $schema;
}

/**
 * Implements hook_install().
 */
function your_module_install(): void {
  // Initial setup after module installation.
  \Drupal::logger('your_module')->info('Your module installed successfully.');
}

/**
 * Implements hook_uninstall().
 */
function your_module_uninstall(): void {
  // Cleanup after module uninstallation.
  \Drupal::logger('your_module')->info('Your module uninstalled.');
}
```

### Database Queries in Services

```php
/**
 * Insert a record.
 */
public function insertRecord(string $external_id, int $uid, array $data): int {
  $this->database->insert('your_module_table')
    ->fields([
      'external_id' => $external_id,
      'uid' => $uid,
      'data' => serialize($data),
      'created' => \time(),
      'changed' => \time(),
    ])
    ->execute();

  return (int) $this->database->lastInsertId();
}

/**
 * Load by external ID.
 */
public function loadByExternalId(string $external_id): ?array {
  return $this->database->select('your_module_table', 't')
    ->fields('t')
    ->condition('external_id', $external_id)
    ->condition('status', 1)
    ->execute()
    ->fetchAssoc();
}

/**
 * Load by user.
 */
public function loadByUid(int $uid): array {
  return $this->database->select('your_module_table', 't')
    ->fields('t')
    ->condition('uid', $uid)
    ->condition('status', 1)
    ->execute()
    ->fetchAllAssoc('id', \PDO::FETCH_ASSOC);
}

/**
 * Update record.
 */
public function updateRecord(int $id, array $fields): void {
  $fields['changed'] = \time();

  $this->database->update('your_module_table')
    ->fields($fields)
    ->condition('id', $id)
    ->execute();
}
```

## Part 4: Frontend Build Pipeline

### package.json

```json
{
  "name": "your-module",
  "version": "1.0.0",
  "description": "Your Module JavaScript",
  "type": "module",
  "scripts": {
    "build": "npm run build:connector && npm run build:ui",
    "build:connector": "vite build --config vite.config.connector.js",
    "build:ui": "vite build --config vite.config.ui.js",
    "dev": "vite --config vite.config.ui.js"
  },
  "dependencies": {
    "your-sdk": "^1.0.0"
  },
  "devDependencies": {
    "@rollup/plugin-commonjs": "^25.0.0",
    "@rollup/plugin-node-resolve": "^15.0.0",
    "vite": "^5.0.0"
  }
}
```

### Vite Config - UI Bundle

**File:** `vite.config.ui.js`

```js
import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig({
  build: {
    lib: {
      entry: resolve(__dirname, 'src/js/your-module-ui.js'),
      name: 'YourModuleUI',
      fileName: 'your-module-ui',
      formats: ['iife'],
    },
    outDir: 'js/dist',
    emptyOutDir: false,
    rollupOptions: {
      output: {
        inlineDynamicImports: false,
      },
    },
  },
});
```

### Vite Config - Connector Bundle (with SDK)

**File:** `vite.config.connector.js`

```js
import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig({
  build: {
    lib: {
      entry: resolve(__dirname, 'src/js/your-module-connector.js'),
      name: 'YourModuleConnector',
      fileName: 'your-module-connector',
      formats: ['iife'],
    },
    outDir: 'js/dist',
    emptyOutDir: false,
    rollupOptions: {
      external: [],
      output: {
        globals: {},
      },
    },
  },
});
```

### Library Registration

**File:** `your_module.libraries.yml`

```yaml
your_module_connector:
  version: 1.0
  js:
    js/dist/your-module-connector.js: { minified: true }
  dependencies:
    - core/drupal
    - core/drupalSettings

your_module_ui:
  version: 1.0
  js:
    js/dist/your-module-ui.js: { minified: true }
  css:
    component:
      src/css/your-module.css: {}
  dependencies:
    - your_module/your_module_connector
    - core/jquery
```

## Part 5: Drupal JavaScript Pattern

### SDK Wrapper (Connector)

**File:** `src/js/your-module-connector.js`

```js
/**
 * Your Module Connector - SDK wrapper.
 */
(function (window) {
  'use strict';

  class YourModuleConnector {
    constructor(config = {}) {
      this.config = {
        endpoint: config.endpoint || '/your-module/api',
        ...config
      };
      this.sdk = null;
      this.state = 'idle';
      this.eventHandlers = {};
    }

    /**
     * Initialize the connector.
     */
    async init() {
      // Initialize your SDK here
      this.state = 'ready';
      this.emit('ready');
      return this;
    }

    /**
     * Check for existing session.
     */
    async checkSession() {
      try {
        // Your session check logic
        const session = this.getSession();
        if (session) {
          this.state = 'connected';
          this.emit('connected', session);
          return session;
        }
      }
      catch (error) {
        console.error('Session check failed:', error);
      }
      return null;
    }

    /**
     * Trigger login flow.
     */
    async login() {
      this.state = 'connecting';
      this.emit('connecting');

      try {
        // Your login logic
        const result = await this.performLogin();
        this.state = 'connected';
        this.emit('connected', result);
        return result;
      }
      catch (error) {
        this.state = 'error';
        this.emit('error', error);
        throw error;
      }
    }

    /**
     * Make API call to backend.
     */
    async apiCall(endpoint, data) {
      const response = await fetch(this.config.endpoint + endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(data),
      });

      if (!response.ok) {
        throw new Error(`API call failed: ${response.statusText}`);
      }

      return response.json();
    }

    /**
     * Event handler registration.
     */
    on(event, callback) {
      if (!this.eventHandlers[event]) {
        this.eventHandlers[event] = [];
      }
      this.eventHandlers[event].push(callback);
    }

    /**
     * Emit event.
     */
    emit(event, data) {
      if (this.eventHandlers[event]) {
        this.eventHandlers[event].forEach(callback => callback(data));
      }
    }
  }

  // Export to window
  window.YourModuleConnector = YourModuleConnector;

})(window);
```

### Drupal Behaviors (UI Logic)

**File:** `src/js/your-module-ui.js`

```js
/**
 * @file
 * Your module UI behaviors.
 */

(function (Drupal, drupalSettings, $) {
  'use strict';

  /**
   * Your module behavior.
   */
  Drupal.behaviors.yourModule = {
    attach: function (context, settings) {
      const config = drupalSettings.yourModule || {};

      // Initialize connector
      if (!window.yourModuleConnector) {
        window.yourModuleConnector = new YourModuleConnector({
          endpoint: config.apiEndpoint || '/your-module',
        });
      }

      const connector = window.yourModuleConnector;

      // Initialize connector
      connector.init().then(() => {
        // Auto-connect if enabled
        if (config.enableAutoConnect) {
          connector.checkSession();
        }
      });

      // Handle connect button
      $('.your-module-connect-btn', context).once('your-module-connect').on('click', function (e) {
        e.preventDefault();
        connector.login();
      });

      // Handle connector events
      connector.on('connecting', () => {
        $('.your-module-container').attr('data-your-module-state', 'connecting');
      });

      connector.on('connected', (data) => {
        $('.your-module-container').attr('data-your-module-state', 'connected');
        // Optionally redirect
        if (config.redirectOnSuccess) {
          window.location.href = config.redirectOnSuccess;
        }
      });

      connector.on('error', (error) => {
        $('.your-module-container').attr('data-your-module-state', 'error');
        Drupal.announce(error.message, 'error');
      });
    }
  };

})(Drupal, drupalSettings, jQuery);
```

## Part 6: Block Plugin Pattern

### Block Plugin

**File:** `src/Plugin/Block/YourBlock.php`

```php
<?php

declare(strict_types=1);

namespace Drupal\your_module\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Your Block' block.
 *
 * @Block(
 *   id = "your_module_block",
 *   admin_label = @Translation("Your Block"),
 *   category = @Translation("Your Module")
 * )
 */
class YourBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The config factory.
   */
  protected readonly ConfigFactoryInterface $configFactory;

  /**
   * Constructs a new YourBlock.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ConfigFactoryInterface $config_factory
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $config = $this->configFactory->get('your_module.settings');

    return [
      '#theme' => 'your_module_block',
      '#attached' => [
        'library' => ['your_module/your_module_ui'],
        'drupalSettings' => [
          'yourModule' => [
            'apiEndpoint' => '/your-module',
            'enableAutoConnect' => $config->get('enable_auto_connect') ?? TRUE,
            'redirectOnSuccess' => '/user',
          ],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockAccess(AccountInterface $account): bool {
    // Example: Only show to anonymous users
    return $account->isAnonymous();
  }

}
```

### Twig Template

**File:** `templates/your-module-block.html.twig`

```twig
{#
/**
 * @file
 * Default theme implementation for Your Module block.
 *
 * Available variables:
 * - attributes: HTML attributes for the containing element.
 */
#}
<div {{ attributes.addClass('your-module-container') }} data-your-module-state="idle">
  <button class="your-module-connect-btn">
    <span>{{ 'Connect'|t }}</span>
  </button>
  <button class="your-module-disconnect-btn visually-hidden">
    <span>{{ 'Disconnect'|t }}</span>
  </button>
  <div class="your-module-status"></div>
</div>
```

### Theme Hook Registration

**File:** `your_module.module`

```php
<?php

/**
 * @file
 * Your module module file.
 */

/**
 * Implements hook_theme().
 */
function your_module_theme(): array {
  return [
    'your_module_block' => [
      'variables' => [
        'attributes' => [],
      ],
    ],
  ];
}
```

## Part 7: Frontend-Backend Integration

### Nonce Endpoint Pattern

**File:** `src/Controller/NonceController.php`

```php
<?php

declare(strict_types=1);

namespace Drupal\your_module\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Nonce controller for secure authentication.
 */
class NonceController implements ContainerInjectionInterface {

  /**
   * The tempstore factory.
   */
  protected readonly PrivateTempStoreFactory $tempStoreFactory;

  /**
   * The config factory.
   */
  protected readonly ConfigFactoryInterface $configFactory;

  /**
   * Constructs a new NonceController.
   */
  public function __construct(
    PrivateTempStoreFactory $temp_store_factory,
    ConfigFactoryInterface $config_factory
  ) {
    $this->tempStoreFactory = $temp_store_factory;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('user.private_tempstore'),
      $container->get('config.factory')
    );
  }

  /**
   * Generate and return a nonce.
   */
  public function generate(): JsonResponse {
    $nonce = $this->generateNonce();
    $lifetime = $this->configFactory->get('your_module.settings')->get('nonce_lifetime') ?? 300;

    // Store nonce in private tempstore
    $store = $this->tempStoreFactory->get('your_module');
    $store->set($nonce, [
      'created' => \time(),
      'lifetime' => $lifetime,
    ]);

    return new JsonResponse([
      'nonce' => $nonce,
      'lifetime' => $lifetime,
    ]);
  }

  /**
   * Generate cryptographically secure nonce.
   */
  protected function generateNonce(): string {
    return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
  }

}
```

### Route for Nonce

**File:** `your_module.routing.yml`

```yaml
your_module.nonce:
  path: '/your-module/nonce'
  methods: [GET]
  defaults:
    _controller: '\Drupal\your_module\Controller\NonceController::generate'
    _title: 'Generate Nonce'
  requirements:
    _permission: 'access content'
```

### Frontend Nonce Fetch

```js
/**
 * Fetch nonce from backend.
 */
async function fetchNonce() {
  const response = await fetch('/your-module/nonce');
  if (!response.ok) {
    throw new Error('Failed to fetch nonce');
  }
  const data = await response.json();
  return data.nonce;
}
```

## Part 8: User Management Pattern

### External Auth Integration

```bash
# Install externalauth module
composer require drupal/externalauth
drush en externalauth -y
```

### User Creation Service

**File:** `src/Service/UserManager.php`

```php
<?php

declare(strict_types=1);

namespace Drupal\your_module\Service;

use Drupal\externalauth\AuthmapInterface;
use Drupal\externalauth\ExternalAuthInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\user\UserInterface;

/**
 * User management service for external auth.
 */
class UserManager {

  /**
   * The external auth service.
   */
  protected readonly ExternalAuthInterface $externalAuth;

  /**
   * The authmap service.
   */
  protected readonly AuthmapInterface $authmap;

  /**
   * The logger.
   */
  protected readonly \Psr\Log\LoggerInterface $logger;

  /**
   * Constructs a new UserManager.
   */
  public function __construct(
    ExternalAuthInterface $external_auth,
    AuthmapInterface $authmap,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->externalAuth = $external_auth;
    $this->authmap = $authmap;
    $this->logger = $logger_factory->get('your_module');
  }

  /**
   * Load or create user from external ID.
   *
   * @param string $external_id
   *   The external identifier.
   * @param string $provider
   *   The auth provider name.
   *
   * @return \Drupal\user\UserInterface
   *   The user account.
   */
  public function loadOrCreateUser(string $external_id, string $provider = 'your_module'): UserInterface {
    $account = $this->externalAuth->load($external_id, $provider);

    if (!$account) {
      // Create new user
      $account = $this->createUser($external_id, $provider);
      $this->logger->info('Created new user for external ID: @id', ['@id' => $external_id]);
    }

    return $account;
  }

  /**
   * Create a new user from external ID.
   */
  protected function createUser(string $external_id, string $provider): UserInterface {
    // Generate username from external ID
    $username = $this->generateUsername($external_id);

    // Generate email
    $email = $this->generateEmail($external_id);

    // Register user via externalauth
    $account = $this->externalAuth->register($username, $provider, $external_id);

    // Set email and activate
    $account->setEmail($email);
    $account->setStatus(TRUE);
    $account->save();

    return $account;
  }

  /**
   * Generate unique username.
   */
  protected function generateUsername(string $external_id): string {
    $base = 'your_module_' . substr($external_id, 0, 8);
    $username = $base;
    $i = 1;

    // Ensure uniqueness
    while (user_load_by_name($username)) {
      $username = $base . '_' . $i++;
    }

    return $username;
  }

  /**
   * Generate email from external ID.
   */
  protected function generateEmail(string $external_id): string {
    return $external_id . '@your-module.local';
  }

  /**
   * Log in the user.
   */
  public function loginUser(UserInterface $account): void {
    user_login_finalize($account);
    $this->logger->info('Logged in user @uid', ['@uid' => $account->id()]);
  }

  /**
   * Link external ID to existing user.
   */
  public function linkAccount(string $external_id, UserInterface $account, string $provider = 'your_module'): void {
    $this->authmap->save($account, $provider, $external_id);
    $this->logger->info('Linked external ID @id to user @uid', [
      '@id' => $external_id,
      '@uid' => $account->id(),
    ]);
  }

}
```

### Service Registration

**File:** `your_module.services.yml`

```yaml
services:
  your_module.user_manager:
    class: Drupal\your_module\Service\UserManager
    arguments:
      - '@externalauth.externalauth'
      - '@externalauth.authmap'
      - '@logger.factory'
```

## Related Skills

For specific aspects of Drupal module development, use these skills:

- **Crypto/Signature Verification**: `/ethereum-php-verify` - EIP-191/EIP-4361 signature verification
- **Testing**: `/drupal-testing-companion` - PHPUnit, Kernel tests, Functional tests
- **Module Polish**: `/drupal-module-polish` - Configuration forms, PHPCS/PHPStan
- **Frontend Debugging**: `/siwe-frontend-debug` - JavaScript debugging for auth flows

## Directory Structure

```
web/modules/custom/your_module/
├── config/
│   ├── install/
│   │   └── your_module.settings.yml
│   └── schema/
│       └── your_module.schema.yml
├── src/
│   ├── Controller/
│   │   ├── ApiController.php
│   │   └── NonceController.php
│   ├── Form/
│   │   └── SettingsForm.php
│   ├── Plugin/
│   │   └── Block/
│   │       └── YourBlock.php
│   └── Service/
│       ├── UserManager.php
│       └── YourService.php
├── templates/
│   └── your-module-block.html.twig
├── tests/
│   ├── Kernel/
│   └── Functional/
├── js/
│   └── dist/                    # Built output
├── src/js/                       # JavaScript source
│   ├── your-module-connector.js
│   └── your-module-ui.js
├── src/css/
│   └── your-module.css
├── package.json
├── vite.config.connector.js
├── vite.config.ui.js
├── your_module.info.yml
├── your_module.libraries.yml
├── your_module.links.menu.yml
├── your_module.permissions.yml
├── your_module.routing.yml
├── your_module.services.yml
└── your_module.install
```

## Quality Checklist

Before considering your module complete:

- [ ] All services registered in .services.yml
- [ ] Database schema defined in hook_schema()
- [ ] REST routes registered with permissions
- [ ] JavaScript builds successfully with npm run build
- [ ] Libraries registered in .libraries.yml
- [ ] Block plugin has visibility rules
- [ ] Twig templates registered in hook_theme()
- [ ] External auth integrated for user management
- [ ] All strings wrapped in $this->t()
- [ ] Dependency injection used (no static Drupal:: calls)
- [ ] Error handling with logging
- [ ] Input validation on all endpoints
