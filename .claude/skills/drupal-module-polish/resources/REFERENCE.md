# Drupal Module Development Reference

Quick reference for common Drupal development patterns, coding standards, and best practices.

## Quick Navigation

- [File Structure](#file-structure)
- [Naming Conventions](#naming-conventions)
- [Common Classes](#common-classes)
- [Dependency Injection](#dependency-injection)
- [Forms](#forms)
- [Controllers](#controllers)
- [Services](#services)
- [Hooks](#hooks)
- [Translations](#translations)
- [Logging](#logging)
- [Common PHPCS Violations](#common-phpcs-violations)
- [Common PHPStan Errors](#common-phpstan-errors)

## File Structure

```
MODULE_NAME/
├── config/
│   ├── install/
│   │   └── MODULE_NAME.settings.yml       # Default config
│   ├── optional/
│   │   └── MODULE_NAME.node_type.yml      # Optional config
│   └── schema/
│       └── MODULE_NAME.schema.yml         # Config schema
├── src/
│   ├── Controller/
│   │   └── MyController.php               # Route controllers
│   ├── Form/
│   │   └── SettingsForm.php               # Config forms
│   ├── Plugin/
│   │   ├── Block/
│   │   │   └── MyBlock.php                # Block plugins
│   │   └── ...
│   └── Service/
│       └── MyService.php                  # Services
├── tests/
│   └── src/
│       └── Functional/
│           └── ModuleTest.php             # Tests
├── assets/
│   └── js/
│       └── script.js                      # JavaScript
├── templates/
│   └── my-template.html.twig              # Twig templates
├── MODULE_NAME.info.yml                   # Module metadata
├── MODULE_NAME.routing.yml                # Routes
├── MODULE_NAME.links.menu.yml             # Menu links
├── MODULE_NAME.links.action.yml           # Action links
├── MODULE_NAME.links.contextual.yml       # Contextual links
├── MODULE_NAME.permissions.yml            # Permissions
├── MODULE_NAME.services.yml               # Service definitions
├── MODULE_NAME.libraries.yml              # Libraries (CSS/JS)
└── README.md                              # Documentation
```

## Naming Conventions

### Files
- **YML files**: `{MODULE_NAME}.{type}.yml`
- **PHP classes**: `{ClassName}.php`
- **Templates**: `{machine-name}.html.twig`

### Classes
- **Namespace**: `Drupal\MODULE_NAME\Subfolder\ClassName`
- **Controllers**: `*Controller` suffix
- **Forms**: `*Form` suffix
- **Plugins**: `*` (no suffix, type is in annotation)
- **Services**: No suffix requirement

### Methods
- **Hooks**: `hook_{hook_name}` or `MODULE_NAME_{hook_name}`
- **Callbacks**: `{module}_{thing}_{action}`
- **Private methods**: `camelCase` (no underscore prefix)

### Variables
- **Variables**: `camelCase`
- **Constants**: `UPPER_SNAKE_CASE`
- **Form keys**: `snake_case`

## Common Classes

### ConfigFormBase
Base class for configuration forms.

```php
use Drupal\Core\Form\ConfigFormBase;

class SettingsForm extends ConfigFormBase {
  protected function getEditableConfigNames() {
    return ['MODULE_NAME.settings'];
  }
}
```

### ControllerBase
Base class for controllers.

```php
use Drupal\Core\Controller\ControllerBase;

class MyController extends ControllerBase {
  // Automatically provides: currentUser(), config(), etc.
}
```

### ContainerFactoryPluginInterface
For plugins with dependency injection.

```php
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

class MyBlock extends BlockBase implements ContainerFactoryPluginInterface {
  public static function create(ContainerInterface $container, ...) {
    return new static(..., $container->get('some.service'));
  }
}
```

## Dependency Injection

### Controller Pattern

```php
<?php

namespace Drupal\MODULE_NAME\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * My custom controller.
 */
class MyController extends ControllerBase {

  protected $currentUser;
  protected $loggerFactory;

  /**
   * Constructs a new MyController object.
   */
  public function __construct(AccountProxyInterface $current_user, LoggerChannelFactoryInterface $logger_factory) {
    $this->currentUser = $current_user;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('logger.factory')
    );
  }

  /**
   * My page callback.
   */
  public function myPage() {
    return [
      '#markup' => $this->t('Hello @user', ['@user' => $this->currentUser->getAccountName()]),
    ];
  }

}
```

### Common Services to Inject

| Service ID | Class | Purpose |
|------------|-------|---------|
| `current_user` | `AccountProxyInterface` | Current user |
| `config.factory` | `ConfigFactoryInterface` | Configuration |
| `logger.factory` | `LoggerChannelFactoryInterface` | Logging |
| `entity_type.manager` | `EntityTypeManagerInterface` | Entity operations |
| `database` | `Connection` | Database queries |
| `state` | `StateInterface` | State system |
| `file_system` | `FileSystemInterface` | File operations |
| `date.formatter` | `DateFormatterInterface` | Date formatting |
| `path.alias_manager` | `AliasManagerInterface` | Path aliases |
| `plugin.manager.block` | `BlockManager` | Block plugins |

## Forms

### ConfigFormBase (Settings Form)

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

    $form['my_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('My Field'),
      '#default_value' => $config->get('my_field'),
      '#description' => $this->t('Description of the field.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('MODULE_NAME.settings')
      ->set('my_field', $form_state->getValue('my_field'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
```

### Common Form Element Types

| Type | Description |
|------|-------------|
| `textfield` | Single-line text input |
| `textarea` | Multi-line text input |
| `number` | Numeric input |
| `email` | Email input |
| `password` | Password input |
| `checkbox` | Single checkbox |
| `checkboxes` | Multiple checkboxes |
| `radios` | Radio buttons |
| `select` | Dropdown select |
| `entity_autocomplete` | Entity reference |
| `managed_file` | File upload |
| `weight` | Weight selector |
| `date` | Date input |
| `tableselect` | Selectable table |
| `details` | Collapsible fieldset |
| `fieldset` | Grouped elements |

### Form Validation

```php
/**
 * {@inheritdoc}
 */
public function validateForm(array &$form, FormStateInterface $form_state) {
  $value = $form_state->getValue('my_field');

  if (strlen($value) < 3) {
    $form_state->setErrorByName('my_field', $this->t('Must be at least 3 characters.'));
  }

  if (!preg_match('/^[a-z0-9_]+$/', $value)) {
    $form_state->setErrorByName('my_field', $this->t('Only lowercase letters, numbers, and underscores allowed.'));
  }
}
```

## Controllers

### Simple Page Controller

```php
<?php

namespace Drupal\MODULE_NAME\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * My page controller.
 */
class MyPageController extends ControllerBase {

  /**
   * Returns a render array for the page.
   */
  public function content() {
    return [
      '#type' => 'markup',
      '#markup' => $this->t('Hello, World!'),
    ];
  }

  /**
   * Returns a page with a specific theme.
   */
  public function themedPage() {
    return [
      '#theme' => 'my_custom_template',
      '#variable' => 'value',
      '#attached' => [
        'library' => ['MODULE_NAME/my-library'],
      ],
    ];
  }

  /**
   * Returns a JSON response (for APIs).
   */
  public function jsonEndpoint() {
    return new JsonResponse([
      'status' => 'success',
      'data' => ['key' => 'value'],
    ]);
  }

}
```

### Route with Parameters

```php
/**
 * View a specific entity by ID.
 */
public function viewEntity($id) {
  $entity = $this->entityTypeManager()->getStorage('my_entity')->load($id);

  if (!$entity) {
    throw new NotFoundHttpException();
  }

  return [
    '#theme' => 'entity_detail',
    '#entity' => $entity,
  ];
}
```

## Services

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

### Service Class

```php
<?php

namespace Drupal\MODULE_NAME\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * My custom service.
 */
class MyService {

  protected $configFactory;
  protected $logger;

  /**
   * Constructs a new MyService object.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory) {
    $this->configFactory = $config_factory;
    $this->logger = $logger_factory->get('MODULE_NAME');
  }

  /**
   * Does something useful.
   */
  public function doSomething($param) {
    $config = $this->configFactory->get('MODULE_NAME.settings');

    $this->logger->info('Doing something with @param', ['@param' => $param]);

    return $result;
  }

}
```

### Use Service

```php
// In a controller or form:
public function __construct(MyService $my_service) {
  $this->myService = $my_service;
}

public static function create(ContainerInterface $container) {
  return new static(
    $container->get('MODULE_NAME.my_service')
  );
}
```

## Hooks

### Implement Hook in .module File

```php
<?php

/**
 * @file
 * Contains MODULE_NAME.module.
 */

use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Implements hook_help().
 */
function MODULE_NAME_help($route_name, RouteMatchInterface $route_match) {
  switch ($route_name) {
    case 'help.page.MODULE_NAME':
      return '<p>' . t('My module help text.') . '</p>';
  }
}

/**
 * Implements hook_page_attachments().
 */
function MODULE_NAME_page_attachments(array &$attachments) {
  $attachments['#attached']['library'][] = 'MODULE_NAME/global';
}

/**
 * Implements hook_preprocess_page().
 */
function MODULE_NAME_preprocess_page(&$variables) {
  $variables['#attached']['drupalSettings']['MODULE_NAME'] = [
    'setting' => 'value',
  ];
}

/**
 * Implements hook_cron().
 */
function MODULE_NAME_cron() {
  // Perform periodic tasks.
  \Drupal::service('MODULE_NAME.my_service')->cleanup();
}

/**
 * Implements hook_theme().
 */
function MODULE_NAME_theme($existing, $type, $theme, $path) {
  return [
    'my_template' => [
      'variables' => [
        'variable1' => NULL,
        'variable2' => [],
      ],
    ],
  ];
}
```

## Translations

### Basic Translation

```php
// Simple string
$this->t('Hello World');

// With placeholder
$this->t('Hello @username', ['@username' => $user->getAccountName()]);

// With passthrough (HTML allowed)
$this->t('Visit <a href=":url">our site</a>', [':url' => 'https://example.com']);

// With plural
$this->formatPlural($count, '1 item', '@count items');
```

### Context-Specific Translation

```php
// When word has multiple meanings
$this->t('May', [], ['context' => 'month']);  // Month of May
$this->t('May', [], ['context' => 'verb']);   // May (permission)
```

### Translation Outside of Classes

```php
// In procedural code (hooks, etc.)
t('Hello World');  // Use plain t(), not $this->t()

\Drupal::translation()->formatPlural($count, '1 item', '@count items');
```

## Logging

### Get Logger

```php
// In a class with DI:
$this->logger = $logger_factory->get('MODULE_NAME');

// Or use LoggerFactory:
$this->logger = \Drupal::logger('MODULE_NAME');
```

### Log Levels

```php
// Detailed debug information
$this->logger->debug('Debug message: @details', ['@details' => $details]);

// Interesting events (user login, config changes)
$this->logger->info('User @uid logged in', ['@uid' => $uid]);

// Normal but significant events
$this->logger->notice('Configuration updated');

// Warning: Exception occurrences that aren't errors
$this->logger->warning('Invalid input: @input', ['@input' => $input]);

// Error: Runtime errors
$this->logger->error('Failed to save: @error', ['@error' => $e->getMessage()]);

// Critical: Critical conditions
$this->logger->critical('Database connection failed');
```

### Log with Exception

```php
try {
  // Some operation
}
catch (\Exception $e) {
  $this->logger->error('Operation failed: @message', [
    '@message' => $e->getMessage(),
  ]);
  $this->logger->debug('Stack trace: @trace', [
    '@trace' => $e->getTraceAsString(),
  ]);
}
```

## Common PHPCS Violations

### 1. Line Length

**Error:** `Line length exceeds 80 characters (found 95)`

**Fix:** Break long lines or allow longer lines in phpcs.xml
```xml
<rule ref="Drupal.Files.LineLength">
  <properties>
    <property name="lineLimit" value="120"/>
    <property name="absoluteLineLimit" value="150"/>
  </properties>
</rule>
```

### 2. Unused Use Statements

**Error:** `Unused use statement`

**Fix:** Remove the unused `use` statement

### 3. Missing Array Type Hint

**Error:** `Missing parameter type`

**Fix:** Add type hint
```php
// Before
function buildForm($form, $form_state) { }

// After
function buildForm(array $form, FormStateInterface $form_state) { }
```

### 4. Missing Return Type

**Error:** `Missing return type`

**Fix:** Add return type declaration
```php
// Before
public function getFormId() { }

// After
public function getFormId(): string { }
```

### 5. Array Double Arrow

**Error:** `Array double arrow not aligned`

**Fix:** Align arrows
```php
// Before
$form = [
  'field' => 'value',
  'another_field' => 'value',
];

// After
$form = [
  'field'        => 'value',
  'another_field' => 'value',
];
```

### 6. Concatenation Spacing

**Error:** `Concatenation operator should be surrounded by spaces`

**Fix:** Add spaces around `.`
```php
// Before
$text = 'Hello'.$name;

// After
$text = 'Hello ' . $name;
```

### 7. Ternary Operator

**Error:** `Ternary operator must be on single line`

**Fix:** Use single-line ternary or if statement
```php
// Before (wrong)
$value = $condition
  ? 'yes'
  : 'no';

// After (correct)
$value = $condition ? 'yes' : 'no';
```

### 8. Control Structure Spacing

**Error:** `Expected 1 space before closing parenthesis`

**Fix:** Add space
```php
// Before
if ($condition){

// After
if ($condition) {
```

## Common PHPStan Errors

### 1. Undefined Variable

**Error:** `Undefined variable: $myVar`

**Fix:** Declare property with type hint
```php
private string $myVar;

public function __construct() {
  $this->myVar = 'default';
}
```

### 2. Property Type Not Specified

**Error:** `Property $config has no type specified`

**Fix:** Add type hint
```php
// Before
protected $configFactory;

// After
protected ConfigFactoryInterface $configFactory;
```

### 3. Unsafe Usage of New Static

**Error:** `Unsafe usage of new static()`

**Fix:** Use dependency injection instead of `new static()`

### 4. Parameter Type Missing

**Error:** `Parameter $form of method buildForm has no type specified`

**Fix:** Add type hint
```php
public function buildForm(array $form, FormStateInterface $form_state) {
```

### 5. Return Type Missing

**Error:** `Return type of getFormId is missing`

**Fix:** Add return type
```php
public function getFormId(): string {
```

### 6. Null Reference

**Error:** `Calling method get() on null`

**Fix:** Add null check or type assertion
```php
$config = $this->config('MODULE_NAME.settings');
if ($config !== null) {
  $value = $config->get('key');
}
```

### 7. Invalid Return Statement

**Error:** `Return type of method is declared as string but should return array`

**Fix:** Fix return type or actual return value
```php
// Either change return type
public function myMethod(): array {

// Or fix return statement
return ['#markup' => 'text'];
```

## Quick Reference: YAML Files

### .info.yml

```yaml
name: 'Module Name'
type: module
description: 'Module description'
core_version_requirement: ^10
package: Custom
dependencies:
  - node
  - views
```

### .routing.yml

```yaml
MODULE_NAME.route_name:
  path: '/my-path/{parameter}'
  defaults:
    _controller: '\Drupal\MODULE_NAME\Controller\MyController::method'
    _title: 'Page Title'
  requirements:
    _permission: 'access content'
    _custom_access: '\Drupal\MODULE_NAME\Controller\MyController::access'
  options:
    parameters:
      parameter:
        type: entity:node
```

### .libraries.yml

```yaml
my_library:
  version: 1.0
  js:
    js/script.js: {}
  css:
    base:
      css/base.css: {}
    component:
      css/component.css: {}
    layout:
      css/layout.css: {}
    theme:
      css/theme.css: {}
  dependencies:
    - core/drupal
    - core/jquery
```

### .permissions.yml

```yaml
my permission:
  title: 'Permission Title'
  description: 'What this permission allows'
  restrict access: true
```

## Drupal Coding Standards Summary

1. **Indentation**: 2 spaces (no tabs)
2. **Line length**: Prefer 80-100 chars, max 120-150
3. **Naming**:
   - Classes: `PascalCase`
   - Methods: `camelCase`
   - Variables: `camelCase`
   - Constants: `UPPER_SNAKE_CASE`
   - Files/arrays: `snake_case`
4. **Whitespace**: Space after comma, around operators
5. **Braces**: Opening brace on same line
6. **Comments**: Doc blocks for classes, methods, properties
7. **Types**: Always use type hints (PHP 7.4+)
8. **Return types**: Always declare return types
9. **Readonly**: Use `readonly` for immutable properties
10. **Translations**: Use `$this->t()`, never concatenate
