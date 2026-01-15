# CLAUDE.md

## Project Overview

Drupal 10.6+ project providing wallet-based authentication using cryptographic signatures (EIP-191). Users authenticate with Ethereum wallets instead of username/password credentials.

**Core Stack:** Drupal 10.6+ (PHP 8.2), External Auth module, Vite frontend builds

## Repository Structure

This project uses a **two-repository architecture**:

1. **Main Project Repository**: Contains Drupal installation and project configuration
2. **wallet_auth Module Repository**: Separate Git repo at `web/modules/custom/wallet_auth/`

When working on the wallet_auth module, note it has its own Git history.

## Directory Structure

```
/Users/proofoftom/Code/os-decoupled/fresh2/
├── composer.json          # Root Composer config
├── vendor/                # PHP dependencies (at project root)
├── phpcs.xml              # PHPCS configuration (Drupal standards)
├── phpstan.neon           # PHPStan configuration (level 6)
├── web/                   # Drupal web root
│   ├── core/              # Drupal core
│   ├── modules/
│   │   ├── contrib/       # Contributed modules
│   │   └── custom/
│   │       └── wallet_auth/  # Wallet Auth module (separate Git repo, has own CLAUDE.md)
│   ├── themes/            # Drupal themes
│   └── phpunit.xml        # PHPUnit configuration
```

## Custom Modules

### wallet_auth
Ethereum wallet authentication module using Wallet as a Protocol (WaaP) and EIP-191 signatures. Enables users to authenticate with MetaMask, WalletConnect, or social logins instead of passwords. See `web/modules/custom/wallet_auth/CLAUDE.md` for module-specific documentation.

## Development Commands

### Code Quality

Run from **project root**:

```bash
# PHPCS - Check coding standards (Drupal)
vendor/bin/phpcs

# PHPCS - Auto-fix issues
vendor/bin/phpcbf

# PHPStan - Static analysis (level 6)
vendor/bin/phpstan analyze
```

### Testing

Run from **web/** directory:

```bash
cd web/

# Run all wallet_auth tests
../vendor/bin/phpunit -c phpunit.xml modules/custom/wallet_auth/tests/

# Run specific test suite
../vendor/bin/phpunit -c phpunit.xml modules/custom/wallet_auth/tests/src/Kernel/
../vendor/bin/phpunit -c phpunit.xml modules/custom/wallet_auth/tests/src/Functional/

# Run specific test file
../vendor/bin/phpunit -c phpunit.xml modules/custom/wallet_auth/tests/src/Kernel/WalletVerificationTest.php
```

**Test Coverage:** 84+ tests across 7 test files (Kernel and Functional)

### Drupal Commands

```bash
drush pm:enable wallet_auth -y   # Enable module
drush cache:rebuild              # Clear caches
drush pm:status                  # Check module status
drush watchdog:show              # View logs
```

## Coding Standards

### PHP (Drupal Standards)

- **PHPCS:** Drupal + DrupalPractice rulesets, parallel processing (8 files)
- **PHPStan:** Level 6 strict analysis

### Key Conventions

1. **Namespace:** `Drupal\wallet_auth\[Subdirectory]`
2. **Services:** Dependency injection via `*.services.yml`
3. **Database:** Drupal Query API (prepared statements)
4. **Security:** Inputs validated, outputs escaped via Twig
5. **Configuration:** Config API with schema in `config/schema/`
6. **Testing:** Drupal test patterns (Kernel, Functional)

### Important Notes

- Run PHPCS/PHPStan from **project root** (configs at root level)
- Run PHPUnit from **web/** directory (bootstrap requires web root)
- Respect two-repo structure (module has separate Git history)

## Dependencies

**PHP (Composer):**
- `drupal/core-recommended: ^10.6`
- `drupal/externalauth: ^2.0`
- `kornrunner/keccak: ^1.1` - Keccak-256 hashing
- `simplito/elliptic-php: ^1.0` - Elliptic curve crypto (secp256k1)
- `drush/drush: ^13.6`
- `drupal/core-dev: ^10.6` - PHPUnit, PHPStan, PHPCS

## Quality Checks Before Commit

```bash
# From project root
vendor/bin/phpcs
vendor/bin/phpstan analyze

# From web/ directory
cd web && ../vendor/bin/phpunit -c phpunit.xml modules/custom/wallet_auth/tests/
```
