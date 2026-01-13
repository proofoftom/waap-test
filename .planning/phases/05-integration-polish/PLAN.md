# Plan: Phase 5 - Integration & Polish

**Project:** Wallet as a Protocol — Drupal Login Module
**Phase:** 5 - Integration & Polish
**Created:** 2025-01-12
**Mode:** yolo

---

## Objective

Add standard Drupal module features: admin configuration form, improve error handling with user-facing messages, ensure coding standards compliance, and update documentation with Phase 4 frontend integration instructions.

---

## Execution Context

@~/.claude/get-shit-done/workflows/plan-phase.md
@~/.claude/get-shit-done/templates/phase-prompt.md

### Phase Context
@.planning/phases/05-integration-polish/RESEARCH.md

### Existing Codebase
- Backend services: WalletVerification, WalletUserManager (Phase 3)
- Frontend integration: Vite-built JS bundles, WalletLoginBlock (Phase 4)
- Quality tools: PHPCS, PHPStan already configured (Phase 1)

### Key Constraints
- Use Drupal Core patterns (ConfigFormBase, messenger service)
- Follow Drupal coding standards (2-space indent, snake_case variables, PascalCase classes)
- All strings must be translatable via `$this->t()`
- Avoid static `\Drupal::` calls where possible (use dependency injection)

---

## Context

### Current State (After Phase 4)
- Backend authentication fully implemented with EIP-191 signature verification
- Frontend wallet connection UI built with Vite and WaaP SDK
- Login button block plugin created
- REST API endpoints functional (`/wallet-auth/authenticate`, `/wallet-auth/nonce`)

### What Phase 5 Adds
This phase focuses on "Drupalification" — making the module production-ready with standard Drupal features:

1. **Configuration Form** - Admin settings at `/admin/config/people/wallet-auth`
   - Configure blockchain network (mainnet, sepolia, etc.)
   - Enable/disable auto-connect behavior
   - Configure nonce lifetime (currently hardcoded to 300s)

2. **Error Handling** - User-facing feedback via messenger service
   - Frontend already has error handling in wallet-auth-ui.js
   - Backend needs structured error responses with logging

3. **Code Quality** - Enforce Drupal coding standards
   - PHPCS (Drupal + DrupalPractice standards)
   - PHPStan (static analysis at level 1)
   - Both tools already configured, just need to run and fix

4. **Documentation** - Complete README for site builders
   - Add frontend integration instructions (placing the block)
   - Configuration guide
   - Troubleshooting section

### Key Architectural Decisions (From Prior Phases)
- **ADR-003**: EIP-191 personal_sign instead of full SIWE
- **ADR-004**: Manual SIWE implementation (avoided react/promise conflict)
- Private tempstore for nonces (5-minute expiry)
- One-to-many wallet-to-user mapping

---

## Tasks

### Task 1: Create Configuration Schema
**File:** `config/schema/wallet_auth.schema.yml`

Define the configuration structure for module settings.

**Steps:**
1. Create `config/schema/wallet_auth.schema.yml` with:
   - Type: `config_object`
   - Label: 'Wallet authentication settings'
   - Mapping fields:
     - `network` (string, label: 'Blockchain network')
     - `enable_auto_connect` (boolean, label: 'Enable auto-connect')
     - `nonce_lifetime` (integer, label: 'Nonce lifetime (seconds)')

**Verification:**
- Schema file exists at `web/modules/custom/wallet_auth/config/schema/wallet_auth.schema.yml`
- Schema defines all configuration fields with proper types

---

### Task 2: Create Default Configuration
**File:** `config/install/wallet_auth.settings.yml`

Provide default values for module configuration.

**Steps:**
1. Create `config/install/wallet_auth.settings.yml` with:
   - `network: 'mainnet'`
   - `enable_auto_connect: true`
   - `nonce_lifetime: 300`

**Verification:**
- Default config file exists at `web/modules/custom/wallet_auth/config/install/wallet_auth.settings.yml`
- Defaults match current hardcoded values

---

### Task 3: Create Settings Form
**File:** `src/Form/SettingsForm.php`

Create the admin configuration form using ConfigFormBase.

**Steps:**
1. Create form class extending `ConfigFormBase`
2. Implement required methods:
   - `getEditableConfigNames()` → return `['wallet_auth.settings']`
   - `getFormId()` → return `'wallet_auth_settings'`
   - `buildForm()` → add form fields with `$this->t()` for labels
   - `submitForm()` → save configuration values
3. Add form fields:
   - Select dropdown for network (mainnet, sepolia, polygon, etc.)
   - Checkbox for enable_auto_connect
   - Number field for nonce_lifetime
4. Use dependency injection for logger (follow WalletVerification pattern)

**Verification:**
- Form class exists with proper namespace `Drupal\wallet_auth\Form`
- Form extends `ConfigFormBase`
- All labels use `$this->t()` for translatibility
- Form saves to `wallet_auth.settings` config

---

### Task 4: Register Admin Route
**File:** `wallet_auth.routing.yml`

Add route for the settings form.

**Steps:**
1. Add route entry to `wallet_auth.routing.yml`:
   - Path: `/admin/config/people/wallet-auth`
   - Defaults: `_form: '\Drupal\wallet_auth\Form\SettingsForm'`
   - Requirements: `_permission: 'administer site configuration'`

**Verification:**
- Route entry exists in `wallet_auth.routing.yml`
- Path is `/admin/config/people/wallet-auth`
- Permission is `'administer site configuration'`

---

### Task 5: Add Link to Admin Menu
**File:** `wallet_auth.links.menu.yml`

Create a menu link for easy access to settings.

**Steps:**
1. Create `wallet_auth.links.menu.yml` with:
   - Title: 'Wallet authentication'
   - Route: `wallet_auth.settings`
   - Parent: `user.admin_index` (under People config section)
   - Description: 'Configure wallet-based authentication settings'

**Verification:**
- Menu link file exists at `web/modules/custom/wallet_auth/wallet_auth.links.menu.yml`
- Link appears under People configuration section

---

### Task 6: Update NonceController to Use Config
**File:** `src/Controller/NonceController.php`

Read nonce_lifetime from config instead of hardcoded value.

**Steps:**
1. Inject `config.factory` service
2. In `generateNonce()`, read from config:
   ```php
   $lifetime = $this->config('wallet_auth.settings')->get('nonce_lifetime');
   ```
3. Pass lifetime to WalletVerification service

**Verification:**
- NonceController uses config factory service
- Nonce lifetime is configurable via admin form

---

### Task 7: Update Frontend to Read Config
**File:** `src/Plugin/Block/WalletLoginBlock.php`

Pass configuration values to frontend via drupalSettings.

**Steps:**
1. Inject `config.factory` service into block plugin
2. In `build()`, read config values:
   - `network`
   - `enable_auto_connect`
3. Add to drupalSettings array:
   ```php
   'network' => $config->get('network'),
   'enableAutoConnect' => $config->get('enable_auto_connect'),
   ```

**Verification:**
- Block plugin reads config values
- Config passed to frontend via drupalSettings
- Frontend can access via `Drupal.settings.walletAuth.network`

---

### Task 8: Review Error Handling in Backend
**Files:** `src/Service/WalletVerification.php`, `src/Controller/AuthenticateController.php`

Audit existing error handling and ensure proper logging.

**Steps:**
1. Review WalletVerification:
   - Confirm all exceptions are logged
   - Verify logger channel is 'wallet_auth'
2. Review AuthenticateController:
   - Confirm error responses are JSON with proper status codes
   - Add logging for authentication failures
3. Check for any missing error paths

**Verification:**
- All exception paths include logging
- Error responses are consistent JSON format
- Security events (failed auth) are logged at WARNING level

---

### Task 9: Run PHPCS and Fix Issues
**Tool:** PHP_CodeSniffer with Drupal + DrupalPractice standards

Check and fix Drupal coding standards violations.

**Steps:**
1. Run PHPCS:
   ```bash
   ./vendor/bin/phpcs --standard=Drupal,DrupalPractice web/modules/custom/wallet_auth/
   ```
2. Review and fix reported issues:
   - Auto-fix where possible: `./vendor/bin/phpcbf`
   - Manual fixes for remaining issues
3. Common issues to check:
   - Missing inline comments
   - Line length > 80 chars
   - Missing whitespace
   - Incorrect indentation
4. Re-run PHPCS until clean

**Verification:**
- PHPCS reports no errors
- Code follows Drupal coding standards

---

### Task 10: Run PHPStan and Fix Issues
**Tool:** PHPStan at level 1

Run static analysis and fix type issues.

**Steps:**
1. Run PHPStan:
   ```bash
   vendor/bin/phpstan analyse web/modules/custom/wallet_auth/
   ```
2. Review and fix reported issues:
   - Missing type hints
   - Undefined variables
   - Invalid method calls
3. Re-run PHPStan until clean

**Verification:**
- PHPStan reports no errors
- Type safety improved

---

### Task 11: Update README with Frontend Instructions
**File:** `README.md`

Add comprehensive documentation for site builders.

**Steps:**
1. Update "Installation" section:
   - Add step for placing the Wallet Login block
   - Add step for configuring settings
2. Add "Configuration" section:
   - Link to admin settings page
   - Explain each configuration option
3. Add "Usage" section:
   - How users authenticate with their wallet
   - What to expect during the flow
4. Add "Troubleshooting" section:
   - Block not appearing (clear cache)
   - Wallet not connecting (check browser wallet)
   - Authentication failing (check logs)
5. Update "Development Roadmap" to mark Phase 4 complete

**Verification:**
- README includes frontend placement instructions
- Configuration options documented
- Troubleshooting section covers common issues
- Roadmap reflects current progress

---

### Task 12: Clear Caches and Verify Module
**Action:** Test all changes in Drupal

**Steps:**
1. Clear all caches: `drush cache:rebuild`
2. Verify admin settings form is accessible:
   - Navigate to `/admin/config/people/wallet-auth`
   - Confirm form renders without errors
3. Save configuration changes
4. Place Wallet Login block on a page via admin UI
5. Test complete authentication flow

**Verification:**
- Admin settings form accessible and functional
- Configuration values persist
- Block can be placed on pages
- Authentication flow works end-to-end

---

## Verification Checklist

After completing all tasks, verify:

### Configuration
- [ ] Settings form accessible at `/admin/config/people/wallet-auth`
- [ ] Form has network, auto-connect, and nonce lifetime fields
- [ ] Configuration saves and persists
- [ ] Default values applied on module install

### Code Quality
- [ ] PHPCS reports 0 errors
- [ ] PHPStan reports 0 errors
- [ ] All strings use `$this->t()` for translatibility
- [ ] No static `\Drupal::` calls (except where unavoidable)

### Documentation
- [ ] README includes frontend placement instructions
- [ ] README includes configuration guide
- [ ] README includes troubleshooting section
- [ ] Inline comments added for complex logic

### Integration
- [ ] Admin menu link appears under People configuration
- [ ] Frontend reads config from drupalSettings
- [ ] Nonce lifetime configurable
- [ ] Caches cleared successfully

---

## Success Criteria

**Phase 5 Complete When:**
1. Admin configuration form is functional at `/admin/config/people/wallet-auth`
2. Module passes PHPCS and PHPStan with 0 errors
3. README comprehensively documents installation, configuration, and usage
4. All configuration is persistable via Drupal config system
5. Error handling includes proper logging and user feedback

**Next Phase:** Phase 6 - Testing & Validation

---

## Output

**Artifacts Created:**
- `config/schema/wallet_auth.schema.yml` - Configuration schema
- `config/install/wallet_auth.settings.yml` - Default config
- `src/Form/SettingsForm.php` - Admin settings form
- `wallet_auth.links.menu.yml` - Admin menu link
- Updated `wallet_auth.routing.yml` - Added settings route
- Updated `src/Controller/NonceController.php` - Use config for nonce lifetime
- Updated `src/Plugin/Block/WalletLoginBlock.php` - Pass config to frontend
- Updated `README.md` - Complete documentation

**Commits Expected:** ~8-10 (one per logical grouping)

---

## Notes

### Task Dependencies
- Tasks 1-5 can be done in parallel (config/routing setup)
- Tasks 6-7 depend on Tasks 1-2 (config must exist first)
- Tasks 8-10 can be done in parallel (code quality)
- Task 11 depends on Tasks 1-7 (documentation needs final config structure)
- Task 12 is last (verification of everything)

### Risk Mitigation
- **Config schema missing:** Drupal will throw errors, test early
- **PHPCS violations:** Some may be auto-fixable with phpcbf
- **Static service calls:** Refactor to dependency injection following existing service pattern

### Performance Considerations
- Configuration is cached by Drupal core
- No performance impact from adding config form
- Reading config in controllers is lightweight

---

*Last updated: 2025-01-12*
