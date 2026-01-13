# Research: Phase 5 - Integration & Polish

**Project:** Wallet as a Protocol — Drupal Login Module
**Phase:** 5 - Integration & Polish
**Last Updated:** 2025-01-12
**Mode:** yolo

---

## Executive Summary

Phase 5 focuses on standard Drupal module development tasks: configuration forms, error handling, and documentation. This is well-trodden territory with established patterns, authoritative documentation, and mature tooling.

**Key Finding:** This phase does NOT require research-phase treatment. These are commodity Drupal features with abundant official documentation, community best practices, and existing module patterns to follow.

---

## Domain Analysis

### What Phase 5 Entails
1. Module configuration form (admin settings UI)
2. Error handling and user feedback (messenger, logging)
3. Drupal coding standards compliance (PHPCS, PHPStan)
4. Documentation (README, inline comments)

### Knowledge Gaps: None
All components of Phase 5 are standard Drupal module development patterns with:
- Official Drupal.org documentation
- Core code examples to follow
- Existing community modules as reference
- Automated tools for standards enforcement

---

## Configuration Forms (Admin Settings)

### Standard Pattern: ConfigFormBase

Drupal provides `ConfigFormBase` for admin settings forms:

```php
namespace Drupal\wallet_auth\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class SettingsForm extends ConfigFormBase {
  protected function getEditableConfigNames() {
    return ['wallet_auth.settings'];
  }

  public function getFormId() {
    return 'wallet_auth_settings';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('wallet_auth.settings');

    // Form fields here
    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('wallet_auth.settings')
      ->set('field', $form_state->getValue('field'))
      ->save();
    parent::submitForm($form, $form_state);
  }
}
```

### Required Files
1. `src/Form/SettingsForm.php` - Form class
2. `config/install/wallet_auth.settings.yml` - Default config
3. `wallet_auth.routing.yml` - Route to admin form
4. `wallet_auth.permissions.yml` - Admin permission

### Common Config Schema
```yaml
# config/schema/wallet_auth.schema.yml
wallet_auth.settings:
  type: config_object
  label: 'Wallet authentication settings'
  mapping:
    network:
      type: string
      label: 'Blockchain network'
    enable_auto_connect:
      type: boolean
      label: 'Enable auto-connect'
```

**Sources:**
- [Working with Configuration Forms](https://www.drupal.org/docs/drupal-apis/configuration-api/working-with-configuration-forms)
- [Defining and using your own configuration](https://www.drupal.org/docs/develop/creating-modules/defining-and-using-your-own-configuration-in-drupal)
- Context7: /drupal/drupal

---

## Error Handling & User Feedback

### User-Facing Messages: Messenger Service

```php
// In controllers or forms
$this->messenger()->addStatus($this->t('Wallet connected successfully.'));
$this->messenger()->addError($this->t('Authentication failed.'));
$this->messenger()->addWarning($this->t('Session expiring soon.'));

// Static context (avoid when possible)
\Drupal::messenger()->addMessage($message, 'status');
```

### Backend Logging

```php
// In services
use Psr\Log\LoggerInterface;

class MyService {
  public function __construct(LoggerInterface $logger) {
    $this->logger = $logger;
  }

  public function doSomething() {
    $this->logger->error('Auth failed: @message', ['@message' => $e->getMessage()]);
  }
}

// Static context (avoid when possible)
\Drupal::logger('wallet_auth')->notice('User authenticated via wallet');
```

### Severity Levels (RfcLogLevel)
- EMERGENCY - System is unusable
- ALERT - Action must be taken immediately
- CRITICAL - Critical conditions
- ERROR - Error conditions
- WARNING - Warning conditions
- NOTICE - Normal but significant
- INFO - Informational messages
- DEBUG - Debug-level messages

**Best Practice:** Use dependency injection for services, avoid static `\Drupal::` calls where possible.

**Sources:**
- [MessengerInterface API](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Messenger%21MessengerInterface.php/10)
- [Logging API Overview](https://www.drupal.org/docs/8/api/logging-api/overview)
- [Logging to Drupal's watchdog system](https://drupalzone.com/tutorial/module-development/105-integrating-with-watchdog)

---

## Coding Standards & Quality Tools

### PHPCS (PHP_CodeSniffer)

```bash
# Install Coder module
composer require --dev drupal/coder

# Run standards check
./vendor/bin/phpcs --standard=Drupal,DrupalPractice web/modules/custom/wallet_auth/

# Auto-fix issues
./vendor/bin/phpcbf --standard=Drupal web/modules/custom/wallet_auth/
```

### PHPStan (Static Analysis)

Already configured in project (`phpstan.neon` at level 1):
```bash
vendor/bin/phpstan analyse web/modules/custom/wallet_auth/
```

### Key Drupal Coding Standards
- PSR-4 for autoloading
- Classes: `PascalCase`
- Methods: `camelCase`
- Variables: `snake_case`
- Indentation: 2 spaces
- Line length: 80-100 chars (soft limit)
- YML files: 2 spaces for indentation

**Sources:**
- [Drupal Coding Standards](https://www.drupal.org/docs/develop/standards)
- [How to Implement Drupal Code Standards](https://drupalize.me/tutorial/how-implement-drupal-code-standards)
- [Drupal Tools and Modules for Coding Standards](https://www.digitalnadeem.com/drupal/drupal-tools-and-modules-used-to-implement-drupal-coding-standards/)

---

## Documentation Best Practices

### README.md Structure

```markdown
# Wallet Authentication

Provides Ethereum wallet-based authentication for Drupal 10 using Wallet as a Protocol.

## Requirements

- Drupal 10
- Web3 browser wallet (MetaMask, etc.)

## Installation

1. Copy module to web/modules/custom/wallet_auth
2. Enable: `drush en wallet_auth`
3. Configure at /admin/config/people/wallet-auth

## Configuration

[Configuration steps]

## Usage

[Usage instructions]

## Troubleshooting

[Common issues and solutions]
```

### Inline Documentation

```php
/**
 * Verifies wallet signatures.
 *
 * @param string $address
 *   The wallet address.
 * @param string $signature
 *   The cryptographic signature.
 *
 * @return bool
 *   TRUE if signature is valid, FALSE otherwise.
 */
public function verifySignature($address, $signature) {
  // Implementation
}
```

### API Documentation Format
- Use `@param`, `@return`, `@throws` tags
- Describe what, not how
- Include usage examples for complex APIs

---

## What NOT to Hand-Roll

### Use Drupal Core, Don't Build:

1. **Configuration System**
   - ✅ Use: `ConfigFormBase`, `config.factory` service
   - ❌ Don't: Custom database tables for settings

2. **Error Handling**
   - ✅ Use: `messenger` service, `logger.factory` service
   - ❌ Don't: Custom message queuing or logging

3. **Form Validation**
   - ✅ Use: `validateForm()`, Form API validators
   - ❌ Don't: Client-side only validation

4. **Permissions**
   - ✅ Use: `permissions.yml`, `->access()` checks
   - ❌ Don't: Custom access control logic

5. **Coding Standards**
   - ✅ Use: PHPCS, PHPStan, Drupal Coder
   - ❌ Don't: Manual style reviews without tooling

---

## Architecture Patterns

### Settings Form Pattern
1. Create form class extending `ConfigFormBase`
2. Define schema file for config structure
3. Provide default config in `config/install/`
4. Register route and permission
5. Use dependency injection for services

### Service Pattern (Already Used)
Phase 3 established proper service patterns with dependency injection. Continue this approach.

### Logging Strategy
- User-facing: Messenger service (status, error, warning)
- Developer-facing: Logger service (notice, info, debug)
- Security events: logger with appropriate severity

---

## Common Pitfalls

### 1. Static Service Calls
```php
// ❌ Avoid
\Drupal::messenger()->addMessage($message);
\Drupal::logger('wallet_auth')->error($msg);

// ✅ Preferred - dependency injection
$this->messenger()->addMessage($message);
$this->logger->error($msg);
```

### 2. Missing Config Schema
- Always create `config/schema/*.schema.yml`
- Enables translation and validation

### 3. Hardcoded Strings
```php
// ❌ Avoid
$form['#title'] = 'Wallet Settings';

// ✅ Use
$form['#title'] = $this->t('Wallet Settings');
```

### 4. Incomplete Error Handling
- Validate both user input and API responses
- Log errors with context variables
- Provide user-friendly error messages

### 5. Missing Permissions
- Always restrict admin forms with proper permissions
- Use existing permissions where possible (`'administer site configuration'`)

---

## Recommended Module References

Study these modules for patterns:
- `contact` - Simple settings form
- `user` - Configuration with multiple sections
- `system` - Comprehensive admin patterns
- `externalauth` - Similar authentication use case

---

## Implementation Checklist

### Configuration Form
- [ ] Create `SettingsForm` extending `ConfigFormBase`
- [ ] Add routing entry for admin path
- [ ] Add permission for settings access
- [ ] Create config schema file
- [ ] Add default config YAML
- [ ] Include validation if needed

### Error Handling
- [ ] Review all existing error paths
- [ ] Add user-facing messages for auth states
- [ ] Add logging for debugging
- [ ] Test error scenarios (wallet rejection, network issues)

### Code Quality
- [ ] Run PHPCS and fix all issues
- [ ] Run PHPStan and address warnings
- [ ] Add inline comments for complex logic
- [ ] Review code against Drupal standards

### Documentation
- [ ] Create comprehensive README
- [ ] Document installation steps
- [ ] Document configuration options
- [ ] Add troubleshooting section
- [ ] Add API docs for services if needed

---

## Tools & Commands

```bash
# PHPCS check
./vendor/bin/phpcs --standard=Drupal,DrupalPractice web/modules/custom/wallet_auth/

# PHPCS auto-fix
./vendor/bin/phpcbf --standard=Drupal web/modules/custom/wallet_auth/

# PHPStan analysis
vendor/bin/phpstan analyse web/modules/custom/wallet_auth/

# Clear Drupal cache (critical for config changes)
drush cache:rebuild
# or
drush cr
```

---

## Next Steps

**Proceed directly to planning (gsd:plan-phase 5)**

This research confirms Phase 5 uses standard Drupal patterns with no special ecosystem considerations. The existing codebase (Phases 1-4) already follows proper patterns to continue.

### Planning Considerations
1. Settings form should configure: blockchain network, auto-connect behavior
2. Error handling should cover: wallet rejection, timeout, signature failures
3. Documentation should target: Drupal site builders, not developers
4. Code quality tools are already configured - just run them

---

## Sources

- [Drupal.org - Configuration API](https://www.drupal.org/docs/drupal-apis/configuration-api/working-with-configuration-forms)
- [Drupal.org - Defining and using configuration](https://www.drupal.org/docs/develop/creating-modules/defining-and-using-your-own-configuration-in-drupal)
- [Drupal.org - Logging API Overview](https://www.drupal.org/docs/8/api/logging-api/overview)
- [Drupal API - MessengerInterface](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Messenger%21MessengerInterface.php/10)
- [Drupalize.Me - Coding Standards](https://drupalize.me/tutorial/how-implement-drupal-code-standards)
- [DrupalZone - Watchdog Logging](https://drupalzone.com/tutorial/module-development/105-integrating-with-watchdog)
- [Medium - Drupal 10 Custom Modules](https://medium.com/@imma.infotech/comprehensive-guide-of-best-practices-for-drupal-development-e73d4ba64029)
- [Context7 - /drupal/drupal](https://context7.com/drupal/drupal/)
