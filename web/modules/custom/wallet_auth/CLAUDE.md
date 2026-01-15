# Wallet Auth Module - Claude Context

## Module Purpose

Drupal 10+ module providing Ethereum wallet-based authentication using Sign-In with Ethereum (SIWE/EIP-4361) and EIP-191 personal_sign. Allows users to authenticate to Drupal using cryptographic signatures from browser wallets (MetaMask, WalletConnect, etc.) instead of passwords.

## Key Services

### wallet_auth.verification (WalletVerification)
- **Purpose**: Cryptographic signature verification and nonce management
- **Location**: `src/Service/WalletVerification.php`
- **Key methods**:
  - `generateNonce()` - Generate cryptographically secure random nonces
  - `storeNonce()` / `verifyNonce()` - Nonce storage in tempstore with expiration
  - `verifySignature()` - EIP-191 signature verification using elliptic-php
  - `validateAddress()` - Ethereum address validation with EIP-55 checksum
  - `deleteNonce()` - Single-use nonce deletion after auth
- **Dependencies**: Uses `kornrunner/keccak` for hashing and `simplito/elliptic-php` for secp256k1 signature verification

### wallet_auth.user_manager (WalletUserManager)
- **Purpose**: Wallet-to-user mapping and Drupal account management
- **Location**: `src/Service/WalletUserManager.php`
- **Key methods**:
  - `loadUserByWalletAddress()` - Find existing user by wallet
  - `linkWalletToUser()` - Associate wallet address with Drupal user
  - `createUserFromWallet()` - Create new user account from wallet
  - `loginOrCreateUser()` - Primary auth flow: login existing or create new
  - `getUserWallets()` - Get all linked wallets for a user
- **Dependencies**: Uses `externalauth` module for external authentication integration

### wallet_auth.access_checker (WalletAuthAccessCheck)
- **Purpose**: Custom access check for routes
- **Location**: `src/Access/WalletAuthAccessCheck.php`

## Directory Structure

```
wallet_auth/
├── src/
│   ├── Service/
│   │   ├── WalletVerification.php
│   │   ├── WalletVerificationInterface.php
│   │   ├── WalletUserManager.php
│   │   └── WalletUserManagerInterface.php
│   ├── Controller/
│   │   ├── NonceController.php           # GET /wallet-auth/nonce
│   │   └── AuthenticateController.php    # POST /wallet-auth/authenticate
│   ├── Plugin/
│   │   ├── Block/
│   │   │   └── WalletLoginBlock.php      # Optional login button block
│   │   └── Menu/
│   │       └── WalletAuthMenuLink.php    # Account menu "Sign In" link
│   ├── Form/
│   │   └── SettingsForm.php              # Admin config form
│   └── Access/
│       └── WalletAuthAccessCheck.php
├── js/
│   ├── src/
│   │   ├── wallet-auth-connector.js      # Wallet connection (WAAP SDK)
│   │   └── wallet-auth-ui.js             # UI interaction logic
│   └── dist/                             # Built JS bundles
├── css/
│   └── wallet-auth.css                   # Component styles
├── tests/
│   └── src/
│       ├── Kernel/
│       │   ├── WalletVerificationTest.php (23 tests)
│       │   └── WalletUserManagerTest.php (18 tests)
│       └── Functional/
│           ├── AuthenticationFlowTest.php (10 tests)
│           ├── WalletLoginBlockTest.php (4 tests)
│           └── SettingsFormTest.php (9 tests)
├── config/install/                       # Default config
├── templates/                            # Twig templates
├── wallet_auth.info.yml
├── wallet_auth.services.yml
├── wallet_auth.routing.yml
├── wallet_auth.links.menu.yml            # Menu link definitions
├── wallet_auth.module                    # Hooks (preprocess, page_attachments)
├── wallet_auth.install                   # Database schema
└── wallet_auth.libraries.yml
```

## Database Schema

**Table**: `wallet_auth_wallet_address`
- `id` (serial, primary key)
- `wallet_address` (varchar 42, unique) - Ethereum address with 0x prefix
- `uid` (int) - Drupal user ID
- `created` (int) - Unix timestamp
- `last_used` (int) - Unix timestamp
- `status` (tinyint) - 1=active, 0=disabled

## Development Commands

### Code Quality
```bash
# PHP CodeSniffer (Drupal coding standards)
vendor/bin/phpcs --standard=phpcs.xml

# PHPStan (Level 6 static analysis)
vendor/bin/phpstan analyse -c phpstan.neon

# Auto-fix coding standards
vendor/bin/phpcbf --standard=phpcs.xml
```

### Testing
Tests must be run from the web/ directory (split directory structure):
```bash
# From project root, navigate to web/
cd web

# Run all tests
../vendor/bin/phpunit -c phpunit.xml modules/custom/wallet_auth/tests/

# Kernel tests only
../vendor/bin/phpunit -c phpunit.xml modules/custom/wallet_auth/tests/src/Kernel/

# Functional tests only
../vendor/bin/phpunit -c phpunit.xml modules/custom/wallet_auth/tests/src/Functional/

# Specific test
../vendor/bin/phpunit -c phpunit.xml modules/custom/wallet_auth/tests/src/Kernel/WalletVerificationTest.php
```

**Test Coverage**: 64 tests, 339 assertions, 82% coverage of critical services

### JavaScript Build
```bash
# Production build (minified)
npm run build

# Development build with watch mode
npm run dev
```

Builds two bundles via Vite:
- `connector.js` - Wallet connection using @human.tech/waap-sdk
- `ui.js` - UI interaction logic

## Key Dependencies

### PHP (via Composer)
- `drupal/externalauth` - External authentication integration (required dependency)
- `kornrunner/keccak` - Keccak-256 hashing for Ethereum
- `simplito/elliptic-php` - Elliptic curve cryptography (secp256k1)

### JavaScript (via npm)
- `@human.tech/waap-sdk` - Wallet as a Protocol SDK
- `vite` - Build tool
- `vite-plugin-node-polyfills` - Node.js polyfills for browser

## Authentication Flow

1. **Nonce Request**: User clicks "Connect Wallet", frontend requests nonce from `/wallet-auth/nonce`
2. **Wallet Connection**: Frontend uses WAAP SDK to connect wallet
3. **Message Signing**: User signs message with nonce using `personal_sign` (EIP-191)
4. **Authentication**: Frontend POSTs to `/wallet-auth/authenticate` with:
   - `wallet_address` - Ethereum address
   - `signature` - Hex-encoded signature
   - `message` - Signed message
   - `nonce` - Base64 nonce
5. **Verification**: Backend validates address, nonce, and signature
6. **User Login**: Creates user if needed, logs in, establishes Drupal session

## Important Notes

- **Automatic Menu Integration**: Module adds "Sign In" to User Account Menu automatically via `WalletAuthMenuLink` plugin - no block placement needed
- **Menu Link Visibility**: For anonymous users, hides default "Log in" and shows wallet auth. For authenticated users, hides wallet auth link.
- **Optional Block**: `WalletLoginBlock` available for custom placement with link/button display modes
- **Separate Git Repository**: This module has its own `.git` directory - it's not tracked in the parent project's git repo
- **No Private Keys**: Module never handles or stores private keys - only wallet addresses
- **Single-Use Nonces**: Nonces expire after 5 minutes (configurable) and are deleted after use
- **Username Format**: Auto-created users get username `wallet_0x1234...` (truncated address)
- **EIP-55 Checksums**: All addresses validated with EIP-55 checksum
- **Split Directory**: Project uses split structure with `vendor/` at root and Drupal in `web/`

## Configuration

Admin settings at `/admin/config/people/wallet-auth`:
- Blockchain network selection (Mainnet, Sepolia, Polygon, etc.)
- Sign-in button text (default: "Sign In")
- Display mode: link (matches nav styling) or button (theme button styling)
- Nonce lifetime (60-3600 seconds)
- WaaP SDK options (authentication methods, allowed socials, dark mode)

## REST API Endpoints

### GET /wallet-auth/nonce
Returns: `{"nonce": "base64encodednonce..."}`

### POST /wallet-auth/authenticate
Request body:
```json
{
  "wallet_address": "0x...",
  "signature": "0x...",
  "message": "Sign this message...",
  "nonce": "base64nonce..."
}
```

Response:
```json
{
  "success": true,
  "uid": 123,
  "username": "wallet_0x1234abcd"
}
```

## Common Tasks

### Menu Link Integration
The module uses these hooks in `wallet_auth.module`:
- `hook_page_attachments()` - Attaches JS library for anonymous users
- `hook_preprocess_menu()` - Controls visibility of login links in account menu

The `WalletAuthMenuLink` plugin (`src/Plugin/Menu/WalletAuthMenuLink.php`):
- Adds "Sign In" link to account menu via `wallet_auth.links.menu.yml`
- Uses `<nolink>` route (JS handles click via `.wallet-auth-trigger` class)
- Gets title from config (`button_text` setting)

### Adding New Test
1. Create test file in `tests/src/Kernel/` or `tests/src/Functional/`
2. Extend `KernelTestBase` or `BrowserTestBase`
3. Run from web/ directory: `../vendor/bin/phpunit -c phpunit.xml modules/custom/wallet_auth/tests/src/.../YourTest.php`

### Modifying Signature Verification
- Edit `src/Service/WalletVerification.php`
- Update `WalletVerificationTest.php` with new test cases
- Run tests to verify crypto operations

### Changing User Creation Logic
- Edit `src/Service/WalletUserManager.php`
- Update `WalletUserManagerTest.php`
- Consider impact on existing users

### Updating Frontend
1. Edit JS source in `js/src/`
2. Run `npm run build` or `npm run dev`
3. Clear Drupal caches: `drush cr`
4. Check browser console for errors

## Security Considerations

- All user inputs validated and sanitized
- Database queries use parameterized statements
- Nonces are cryptographically random and single-use
- Signature verification uses established crypto libraries
- CSRF protection via signature verification
- XSS prevention via proper output escaping
- Replay attack prevention via nonce expiration

## Drupal Version Support

- Drupal 10.6+
- Drupal 11 compatible (`core_version_requirement: ^10 || ^11`)
- PHP 8.2+ required
