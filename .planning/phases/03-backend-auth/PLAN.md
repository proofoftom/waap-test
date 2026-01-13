# PLAN: Phase 3 — Backend Authentication System

**Project:** Wallet as a Protocol Drupal Login Module
**Phase:** 3 — Backend Authentication System
**Created:** 2025-01-12
**Status:** Ready to Execute

---

## Objective

Implement the Drupal backend services for wallet-based authentication. This phase creates the server-side infrastructure that verifies wallet signatures, manages wallet-to-user mapping, and provides REST endpoints for the frontend to authenticate users.

**Success Criteria:**
- Database schema stores wallet addresses and maps them to Drupal users
- Signature verification service validates cryptographic proofs of wallet ownership
- REST endpoint accepts signed messages and returns authenticated sessions
- Authentication provider integrates with Drupal's user system
- New users are auto-created on first successful authentication
- Existing users are logged in on subsequent authentications

---

## Context

### Project State
- **Mode:** yolo — Execute with minimal confirmation gates
- **Current Status:** Starting Phase 3
- **Completed Phases:**
  - Phase 1: Foundation & Environment Setup (DDEV + Drupal 10 running)
  - Phase 2: Wallet as a Protocol Integration Research (WaaP spec understood)

### Key Research Findings

From `.planning/phases/02-protocol-integration/RESEARCH.md`:

**WaaP Authentication Flow:**
1. Frontend calls `window.waap.login()` and gets wallet address
2. Frontend requests a nonce from backend
3. Backend generates cryptographically random nonce, stores in session
4. Frontend signs message using `personal_sign` (EIP-191)
5. Frontend sends address, signature, and message to backend
6. Backend verifies signature matches address
7. Backend finds or creates Drupal user associated with wallet address
8. Backend logs user in and returns session

**Architecture Decisions:**
- **ADR-003:** Use SIWE (EIP-4361) for authentication — industry standard, better security
- **ADR-004:** Use External Auth module for user creation/login — battle-tested
- **Signature Verification:** Use `iltumio/siwe-php` library — native EIP-4361 implementation with built-in parsing and validation

**Drupal Integration Pattern:**
- Authentication provider plugin (`@ConsumerAuthentication`)
- External Auth service (`externalauth.externalauth`)
- REST endpoint for signature verification
- Database table mapping wallet_address → uid (one-to-many: user can have multiple wallets)

### Module Structure

Current state (`web/modules/custom/wallet_auth/`):
```
├── wallet_auth.info.yml       ✅ Exists
├── wallet_auth.module         ✅ Exists (empty)
├── wallet_auth.routing.yml    ✅ Exists (empty)
├── wallet_auth.services.yml   ✅ Exists (empty)
├── wallet_auth.permissions.yml ✅ Exists (empty)
├── composer.json              ✅ Exists
├── README.md                  ✅ Exists
├── src/                       ⚠️ Empty
├── config/install/            ⚠️ Empty
├── config/schema/             ⚠️ Empty
└── tests/                     ⚠️ Empty
```

### Known Constraints

- Must follow Drupal coding standards (PSR-4, PHPCS compliance)
- Must use Drupal's database API (no raw SQL)
- Must integrate with Drupal's user system (not bypass it)
- Must use External Auth module for user management
- Signature verification must use established crypto libraries (no hand-rolled crypto)
- Nonces must be cryptographically random and single-use
- Must handle edge cases: wallet already registered to different user, user has multiple wallets

---

## Tasks

### Task 1: Install PHP Dependencies

Add required PHP packages for cryptographic operations.

**Steps:**
1. Run: `ddev composer require iltumio/siwe-php`
2. Verify packages are installed: Check `composer.json` and `vendor/`

**Rationale:**
- `iltumio/siwe-php` is a native PHP implementation of EIP-4361 (Sign-In with Ethereum)
- Provides full SIWE message parsing, generation, and validation
- Includes signature verification via `web3p/web3.php` (included as dependency)
- Handles domain binding, nonce validation, expiration time, and EIP-1271 contract wallets
- Test suite with official SIWE spec vectors — proven correctness
- Do NOT implement cryptography from scratch — critical security requirement

**Dependencies:** This package will also install:
- `web3p/web3.php` (0.3.2) — Actual signature verification
- `kornrunner/keccak` — Keccak hashing
- `simplito/elliptic-php` — Elliptic curve operations
- `nesbot/carbon` — Date/time handling
- `guzzlehttp/promises` — Promise support

**Verification:**
- `composer.json` contains `iltumio/siwe-php` in require section
- `vendor/iltumio/siwe-php/` directory exists
- `ddev exec composer show` lists iltumio/siwe-php and its dependencies

**Done:** SIWE package and all crypto dependencies installed and autoloadable

---

### Task 2: Install External Auth Module

Install and enable the External Auth module for user management.

**Steps:**
1. Run: `ddev composer require drupal/externalauth`
2. Run: `ddev drush pm:enable externalauth -y`
3. Verify: `ddev drush pm:list --type=module --status=enabled | grep externalauth`

**Rationale:**
- External Auth is Drupal standard for external authentication providers
- Provides `externalauth.externalauth` service with `loginRegister()`, `register()`, `login()` methods
- Handles user account creation and linking robustly
- Used by other auth modules (web3_auth, siwe_login)

**Verification:**
- `drupal/externalauth` in composer.json
- `web/modules/contrib/externalauth` exists
- `ddev drush pm:list` shows externalauth as enabled
- Service `externalauth.externalauth` is registered (check `ddev drush debug:container | grep externalauth`)

**Done:** External Auth module installed and enabled

---

### Task 3: Create Database Schema

Define database table for wallet address to user mapping.

**Files:** `web/modules/custom/wallet_auth/config/install/wallet_auth.schema.yml`

**Action:**
Create Drupal config schema file defining:
- Table name: `wallet_auth_wallet_address`
- Fields:
  - `id` (primary key, serial)
  - `wallet_address` (varchar 42, unique) — Ethereum address with 0x prefix
  - `uid` (int, not null) — Reference to users.uid
  - `created` (int, not null) — Unix timestamp
  - `last_used` (int, not null) — Unix timestamp of last auth
  - `status` (int, default 1) — Active/inactive (for future use)
- Indexes:
  - Primary key on `id`
  - Unique index on `wallet_address`
  - Index on `uid` (for reverse lookup: user's wallets)

Use Drupal's Schema API format. Reference: https://www.drupal.org/docs/develop/coding-standards/schema-api-documentation

**Do NOT:**
- Use raw SQL in `.install` files (use Schema API or Config Entity)
- Store private keys or seed phrases (NEVER do this)

**Verification:**
- Schema file exists at `config/install/wallet_auth.schema.yml`
- Valid YAML syntax
- Table definition follows Drupal Schema API format
- Field types match specification

**Done:** Database schema defined, ready for module installation

---

### Task 4: Create Database Installation Hook

Implement hook_install to create the wallet address table.

**Files:** `web/modules/custom/wallet_auth/wallet_auth.install`

**Action:**
Create PHP file with:
1. `hook_install()` implementation that creates the table using `drupal_install_schema()`
2. `hook_schema()` implementation that returns the schema array
3. `hook_uninstall()` implementation that drops the table

Schema structure:
```php
$schema['wallet_auth_wallet_address'] = [
  'description' => 'Maps wallet addresses to Drupal users',
  'fields' => [
    'id' => [
      'type' => 'serial',
      'not null' => TRUE,
    ],
    'wallet_address' => [
      'type' => 'varchar',
      'length' => 42,
      'not null' => TRUE,
    ],
    'uid' => [
      'type' => 'int',
      'unsigned' => TRUE,
      'not null' => TRUE,
    ],
    'created' => [
      'type' => 'int',
      'not null' => TRUE,
    ],
    'last_used' => [
      'type' => 'int',
      'not null' => TRUE,
    ],
    'status' => [
      'type' => 'int',
      'default' => 1,
    ],
  ],
  'primary key' => ['id'],
  'unique keys' => [
    'wallet_address' => ['wallet_address'],
  ],
  'indexes' => [
    'uid' => ['uid'],
  ],
];
```

**Verification:**
- `wallet_auth.install` file exists in module root
- `hook_schema()` returns valid schema array
- `hook_install()` calls `drupal_install_schema('wallet_auth')`
- `hook_uninstall()` calls `drupal_uninstall_schema('wallet_auth')`

**Done:** Installation hook creates table on module enable

---

### Task 5: Create Wallet Verification Service

Implement service for cryptographic signature verification.

**Files:** `web/modules/custom/wallet_auth/src/Service/WalletVerification.php`

**Action:**
Create PHP service class with:
- Namespace: `Drupal\wallet_auth\Service`
- Constructor injection: `database`, `logger.channel.default`, `datetime.time`
- Methods:
  1. `generateNonce(): string` — Generate cryptographically random nonce (32 bytes, base64 encoded)
  2. `storeNonce(string $nonce, string $walletAddress): void` — Store nonce in temp storage (Drupal tempstore or private tempstore) with 5-minute expiry
  3. `verifyNonce(string $nonce, string $walletAddress): bool` — Verify nonce exists and not expired
  4. `verifySignature(string $message, string $signature, string $walletAddress): bool` — Verify signature matches address
  5. `validateAddress(string $walletAddress): bool` — Validate Ethereum address format and checksum

**Verification Implementation:**
Use `iltumio/siwe-php` for SIWE message parsing and signature verification:
```php
use Iltumio\SiwePhp\SiweMessage;

// Parse and verify SIWE message
$siweMessage = new SiweMessage($siweMessageString);

$result = $siweMessage->verify([
    'signature' => $signature,
    'domain' => $this->getDomain(),  // Domain binding
    'nonce' => $storedNonce,          // Nonce validation
])->wait();

return $result['success'] === true;
```

**Note:** The SIWE library handles:
- Message parsing (string → structured object)
- Domain binding validation
- Nonce validation
- Time-based validation (expiration, notBefore)
- Signature verification via EIP-4361
- EIP-1271 contract wallet support (if needed)

**Nonce Storage:**
Use Drupal's `private_tempstore` factory for session-based storage:
```php
$this->tempStoreFactory->get('wallet_auth')->set($nonce, [
  'wallet_address' => $walletAddress,
  'created' => $this->time->getRequestTime(),
]);
```

**Security:**
- Nonce MUST be cryptographically random: `\Drupal::service('password')->require(32)`
- Nonce MUST expire after 5 minutes
- Nonce MUST be single-use (delete after verification)

**Verification:**
- Service class exists at `src/Service/WalletVerification.php`
- Implements all required methods
- Uses dependency injection correctly
- Uses crypto library for verification (no custom crypto)
- Nonce uses Drupal's random bytes generator

**Done:** Wallet verification service with crypto library integration

---

### Task 6: Register Wallet Verification Service

Register the verification service in Drupal's service container.

**Files:** `web/modules/custom/wallet_auth/wallet_auth.services.yml`

**Action:**
Add service definition:
```yaml
services:
  wallet_auth.verification:
    class: Drupal\wallet_auth\Service\WalletVerification
    arguments:
      - '@database'
      - '@logger.channel.default'
      - '@datetime.time'
      - '@tempstore.private'
```

**Verification:**
- Service registered in wallet_auth.services.yml
- Service name: `wallet_auth.verification`
- All required dependencies injected
- YAML is valid
- `ddev drush debug:container wallet_auth.verification` shows service definition

**Done:** Verification service injectable via dependency injection

---

### Task 7: Create Wallet User Manager Service

Implement service for user lookup, creation, and linking.

**Files:** `web/modules/custom/wallet_auth/src/Service/WalletUserManager.php`

**Action:**
Create PHP service class with:
- Namespace: `Drupal\wallet_auth\Service`
- Constructor injection: `database`, `externalauth.externalauth`, `entity_type.manager`, `logger.channel.default`, `datetime.time`
- Methods:
  1. `loadUserByWalletAddress(string $walletAddress): ?UserInterface` — Query wallet_auth_wallet_address table, return user if found
  2. `linkWalletToUser(string $walletAddress, int $uid): void` — Insert wallet address mapping, update last_used timestamp
  3. `createUserFromWallet(string $walletAddress): UserInterface` — Create new Drupal user via External Auth, link wallet
  4. `loginOrCreateUser(string $walletAddress): UserInterface` — Main auth flow: load existing or create new
  5. `getUserWallets(int $uid): array` — Return all wallet addresses for a user

**User Creation Logic:**
```php
public function createUserFromWallet(string $walletAddress): UserInterface {
  $username = 'wallet_' . substr($walletAddress, 0, 8); // e.g., wallet_0x1234abcd
  $email = $username . '@wallet.local'; // Placeholder email

  // Use External Auth to create user
  $account = $this->externalAuth->register($username, 'wallet_auth');
  $account->setEmail($email);
  $account->activate();
  $account->save();

  // Link wallet to user
  $this->linkWalletToUser($walletAddress, $account->id());

  return $account;
}
```

**Edge Cases:**
- If wallet address already linked to different user: return existing user (do NOT re-link)
- If user has multiple wallets: return user record, allow wallet linking
- Username collision: append random suffix if needed

**Verification:**
- Service class exists at `src/Service/WalletUserManager.php`
- Implements all required methods
- Uses External Auth service for user creation
- Handles wallet address queries correctly
- Handles username collision

**Done:** User manager service with External Auth integration

---

### Task 8: Register Wallet User Manager Service

Register the user manager service in Drupal's service container.

**Files:** `web/modules/custom/wallet_auth/wallet_auth.services.yml`

**Action:**
Add service definition to existing `wallet_auth.services.yml`:
```yaml
services:
  wallet_auth.verification:
    # ... existing definition ...

  wallet_auth.user_manager:
    class: Drupal\wallet_auth\Service\WalletUserManager
    arguments:
      - '@database'
      - '@externalauth.externalauth'
      - '@entity_type.manager'
      - '@logger.channel.default'
      - '@datetime.time'
```

**Verification:**
- Service registered in wallet_auth.services.yml
- Service name: `wallet_auth.user_manager`
- All required dependencies injected (including externalauth)
- YAML is valid
- `ddev drush debug:container wallet_auth.user_manager` shows service definition

**Done:** User manager service injectable via dependency injection

---

### Task 9: Create REST Controller for Authentication

Implement REST endpoint for signature verification and user login.

**Files:** `web/modules/custom/wallet_auth/src/Controller/AuthenticateController.php`

**Action:**
Create PHP controller class with:
- Namespace: `Drupal\wallet_auth\Controller`
- Route: `/wallet-auth/authenticate`
- Methods: `authenticate(Request $request)` — POST endpoint
- Constructor injection: `wallet_auth.verification`, `wallet_auth.user_manager`, `current_user`

**Request Format (POST):**
```json
{
  "wallet_address": "0x1234567890123456789012345678901234567890",
  "signature": "0xabcdef...",
  "message": "example.com wants you to sign in...",
  "nonce": "base64encodednonce..."
}
```

**Response Format:**
- Success (200): `{ "success": true, "uid": 123, "username": "wallet_0x1234" }`
- Invalid signature (401): `{ "success": false, "error": "Invalid signature" }`
- Invalid nonce (400): `{ "success": false, "error": "Invalid or expired nonce" }`
- Invalid address (400): `{ "success": false, "error": "Invalid wallet address" }`

**Authentication Flow:**
```php
public function authenticate(Request $request) {
  // 1. Parse request
  $data = json_decode($request->getContent(), TRUE);
  $walletAddress = $data['wallet_address'] ?? '';
  $signature = $data['signature'] ?? '';
  $message = $data['message'] ?? '';
  $nonce = $data['nonce'] ?? '';

  // 2. Validate address format
  if (!$this->verification->validateAddress($walletAddress)) {
    return new JsonResponse(['success' => false, 'error' => 'Invalid wallet address'], 400);
  }

  // 3. Verify nonce
  if (!$this->verification->verifyNonce($nonce, $walletAddress)) {
    return new JsonResponse(['success' => false, 'error' => 'Invalid or expired nonce'], 400);
  }

  // 4. Verify signature
  if (!$this->verification->verifySignature($message, $signature, $walletAddress)) {
    return new JsonResponse(['success' => false, 'error' => 'Invalid signature'], 401);
  }

  // 5. Load or create user
  $user = $this->userManager->loginOrCreateUser($walletAddress);

  // 6. Log user in
  user_login_finalize($user);

  // 7. Return success
  return new JsonResponse([
    'success' => true,
    'uid' => $user->id(),
    'username' => $user->getAccountName(),
  ]);
}
```

**Security:**
- Validate all inputs
- Verify nonce before signature (prevents replay attacks)
- Delete nonce after verification (single-use)
- Use `user_login_finalize()` to properly log user in
- Do NOT reveal whether user exists or not in error messages

**Verification:**
- Controller class exists at `src/Controller/AuthenticateController.php`
- Extends `ControllerBase`
- Implements `authenticate()` method
- Uses injected services correctly
- Returns JsonResponse with correct format
- Handles all error cases

**Done:** REST controller for wallet authentication

---

### Task 10: Add REST Route

Register the authentication endpoint in Drupal's routing system.

**Files:** `web/modules/custom/wallet_auth/wallet_auth.routing.yml`

**Action:**
Add route definition:
```yaml
wallet_auth.authenticate:
  path: '/wallet-auth/authenticate'
  defaults:
    _controller: '\Drupal\wallet_auth\Controller\AuthenticateController::authenticate'
    _title: 'Wallet Authentication'
  methods: [POST]
  requirements:
    _access: 'TRUE'
    _csrf_token: 'TRUE'
  options:
    _auth: [ 'basic_auth' ]
    no_cache: 'TRUE'
```

**Security Considerations:**
- `_csrf_token: 'TRUE'` — Require CSRF token (or implement custom token validation)
- `_access: 'TRUE'` — Allow anonymous access (authentication happens inside controller)
- Consider adding rate limiting (can be added in Phase 5)

**Verification:**
- Route exists in wallet_auth.routing.yml
- Path is `/wallet-auth/authenticate`
- Methods limited to POST
- YAML is valid
- `ddev drush route:debug | grep wallet_auth` shows route

**Done:** Authentication route registered and accessible

---

### Task 11: Add Permission for Wallet Login

Define permission for accessing wallet authentication.

**Files:** `web/modules/custom/wallet_auth/wallet_auth.permissions.yml`

**Action:**
Add permission definition:
```yaml
authenticate with wallet:
  title: 'Authenticate with wallet'
  description: 'Allow users to authenticate using their wallet address.'
  restrict access: false
```

**Note:** This permission is primarily for documentation and future use. The authentication endpoint is anonymous-accessible by design.

**Verification:**
- Permission exists in wallet_auth.permissions.yml
- YAML is valid
- `ddev drush user:list:permissions | grep "authenticate with wallet"` shows permission

**Done:** Wallet authentication permission defined

---

### Task 12: Uninstall and Reinstall Module

Trigger database schema creation by reinstalling the module.

**Steps:**
1. Uninstall module: `ddev drush pm:uninstall wallet_auth -y`
2. Install module: `ddev drush pm:enable wallet_auth -y`
3. Verify: `ddev drush pm:list --type=module --status=enabled | grep wallet_auth`
4. Check database: `ddev exec mysql -uroot -proot -e "USE db; DESCRIBE wallet_auth_wallet_address;"` (replace 'db' with actual database name)

**Rationale:**
- Reinstalling triggers `hook_install()` which creates the schema
- Ensures clean slate for testing
- Verifies schema installation works correctly

**Verification:**
- Module shows as enabled
- Table `wallet_auth_wallet_address` exists in database
- Table has correct structure (id, wallet_address, uid, created, last_used, status)
- Indexes are created (unique on wallet_address, index on uid)

**Done:** Database table created with correct schema

---

### Task 13: Test Nonce Generation and Storage

Manually test nonce generation via Drupal console or test script.

**Steps:**
1. Create a test script in Drupal root: `test-nonce.php`
2. Run: `ddev exec php test-nonce.php`
3. Verify: Nonce is 32+ characters, base64-encoded
4. Check tempstore: Nonce is stored with timestamp

**Test Script:**
```php
<?php
require __DIR__ . '/vendor/autoload.php';

use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;

$request = Request::createFromGlobals();
$kernel = DrupalKernel::createFromRequest($request, $autoloader, 'prod');
$kernel->boot();
$kernel->preHandle($request);

$verification = \Drupal::service('wallet_auth.verification');
$nonce = $verification->generateNonce();
echo "Generated nonce: " . $nonce . "\n";
echo "Nonce length: " . strlen($nonce) . "\n";

// Test storage
$verification->storeNonce($nonce, '0x1234567890123456789012345678901234567890');
echo "Nonce stored successfully\n";
```

**Verification:**
- Script runs without errors
- Nonce is generated (non-empty string)
- Nonce is base64-encoded (valid characters only)
- Nonce is stored in tempstore

**Done:** Nonce generation and storage working

---

### Task 14: Test Address Validation

Verify wallet address validation logic.

**Steps:**
1. Extend test script to test valid and invalid addresses
2. Test valid: `0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb` (with checksum)
3. Test invalid: `0x1234` (too short)
4. Test invalid: `0xGGGGGGGGGGGGGGGGGGGGGGGGGGGGGGGGGGGGGGGG` (invalid hex)
5. Test invalid: `742d35Cc6634C0532925a3b844Bc9e7595f0bEb` (missing 0x prefix)

**Verification:**
- Valid addresses return TRUE
- Invalid addresses return FALSE
- Checksum validation works (mixed case validated)
- Address length validation works (must be 42 characters with 0x)
- Hex character validation works (0-9, a-f, A-F)

**Done:** Address validation working correctly

---

### Task 15: Test User Creation via WalletUserManager

Verify user creation and wallet linking logic.

**Steps:**
1. Create test script `test-user-creation.php`
2. Test creating new user from wallet address
3. Test loading existing user by wallet address
4. Test linking multiple wallets to same user
5. Test that duplicate wallet addresses are rejected

**Test Script:**
```php
<?php
// ... bootstrap Drupal ...

$userManager = \Drupal::service('wallet_auth.user_manager');

// Test 1: Create new user
$wallet1 = '0x1111111111111111111111111111111111111111';
$user1 = $userManager->createUserFromWallet($wallet1);
echo "Created user: " . $user1->getAccountName() . " (UID: " . $user1->id() . ")\n";

// Test 2: Load existing user
$user2 = $userManager->loadUserByWalletAddress($wallet1);
echo "Loaded user: " . $user2->getAccountName() . " (UID: " . $user2->id() . ")\n";
assert($user1->id() === $user2->id(), "User IDs should match");

// Test 3: Link second wallet to same user
$wallet2 = '0x2222222222222222222222222222222222222222';
$userManager->linkWalletToUser($wallet2, $user1->id());

// Test 4: Get all wallets for user
$wallets = $userManager->getUserWallets($user1->id());
echo "User has " . count($wallets) . " wallets\n";
assert(count($wallets) === 2, "User should have 2 wallets");

echo "All tests passed!\n";
```

**Verification:**
- User is created with correct username format (wallet_0x1234...)
- User account is active
- Wallet address is linked in database
- Loading user by wallet returns correct user
- Multiple wallets can be linked to same user
- Database records are created correctly

**Done:** User creation and wallet linking working

---

### Task 16: Test Signature Verification

Verify signature verification logic (can use test vectors).

**Steps:**
1. Use known test vectors from Ethereum documentation
2. Test valid signature verification
3. Test invalid signature rejection
4. Test signature from wrong address rejection

**Test Vectors (example):**
```php
$message = "Hello, world!";
$address = "0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb";
$signature = "0x..."; // Valid signature for this message/address
```

**Verification:**
- Valid signatures return TRUE
- Invalid signatures return FALSE
- Signatures from wrong addresses return FALSE
- Signature recovery works correctly

**Note:** If test vectors are not readily available, this verification can be deferred to integration testing with actual wallet signing in Phase 4.

**Done:** Signature verification logic verified (or deferred to Phase 4)

---

### Task 17: Test REST Endpoint

Verify the authentication endpoint works end-to-end.

**Steps:**
1. Start DDEV: `ddev start`
2. Ensure Drupal is running: `ddev launch`
3. Use curl to test endpoint with test data:
```bash
ddev exec curl -X POST https://wallet-auth-drupal.ddev.site/wallet-auth/authenticate \
  -H "Content-Type: application/json" \
  -d '{"wallet_address":"0x1234...","signature":"0xabcd...","message":"test","nonce":"..."}'
```
4. Verify response format (success or error)
5. Check Drupal logs for any errors: `ddev drush log:watch`

**Verification:**
- Endpoint returns 200, 400, or 401 status code
- Response body is valid JSON
- Success response includes uid and username
- Error responses include error message
- No PHP errors in logs
- CSRF token validation works (or implement custom token)

**Note:** Full end-to-end testing with real wallet signatures will happen in Phase 4 when frontend is implemented.

**Done:** REST endpoint accessible and returns correct responses

---

### Task 18: Run Code Quality Checks

Verify code meets Drupal coding standards.

**Steps:**
1. Run PHPCS: `ddev exec vendor/bin/phpcs web/modules/custom/wallet_auth/src/`
2. Run PHPStan: `ddev exec vendor/bin/phpstan analyse web/modules/custom/wallet_auth/src/`
3. Fix any errors or warnings found

**Verification:**
- PHPCS reports no errors (warnings are acceptable)
- PHPStan reports no errors at level 1
- All code follows Drupal coding standards
- PSR-4 autoloading is correct

**Done:** Code passes quality checks

---

### Task 19: Create Service Tests (Optional)

Create unit tests for verification and user manager services.

**Files:** `web/modules/custom/wallet_auth/tests/src/Unit/WalletVerificationTest.php`, `WalletUserManagerTest.php`

**Action:**
Create unit tests for:
- Nonce generation and validation
- Address validation
- User creation and loading
- Wallet linking

**Note:** This is optional for Phase 3 completion. Tests can be added in Phase 5 or 6.

**Verification:**
- Test files exist
- Tests can be run via PHPUnit
- Tests cover critical paths

**Done:** Unit tests created (optional)

---

### Task 20: Document Backend Services

Add inline documentation and update README.

**Files:** `web/modules/custom/wallet_auth/README.md`, service PHP files

**Action:**
1. Add PHPDoc comments to all service methods
2. Document REST endpoint in README:
   - Endpoint URL
   - Request format
   - Response format
   - Example curl commands
3. Document architecture decisions
4. Add API documentation section

**Verification:**
- All service methods have PHPDoc comments
- README documents REST endpoint
- API usage is clear from documentation
- Code is self-documenting

**Done:** Backend services documented

---

## Execution Order

Tasks must be completed in sequential order due to dependencies:

```
1 → 2 → 3 → 4 → 5 → 6 → 7 → 8 → 9 → 10 → 11 → 12 → 13 → 14 → 15 → 16 → 17 → 18 → 19 → 20
```

**Checkpoints:**
- **After Task 2:** External Auth module available
- **After Task 8:** Both backend services registered
- **After Task 12:** Database schema installed
- **After Task 17:** Full authentication flow works

---

## Success Criteria

Phase 3 is complete when ALL of the following are true:

1. **Dependencies Installed:** PHP crypto packages and External Auth module installed
2. **Database Schema:** `wallet_auth_wallet_address` table exists with correct structure
3. **Services Registered:** `wallet_auth.verification` and `wallet_auth.user_manager` are available
4. **REST Endpoint:** `/wallet-auth/authenticate` is accessible and returns JSON responses
5. **User Creation:** New users are created on first wallet authentication
6. **User Login:** Existing users are logged in on subsequent wallet authentication
7. **Wallet Linking:** Wallet addresses are correctly mapped to users in database
8. **Signature Verification:** Cryptographic verification works (or deferred to Phase 4)
9. **Code Quality:** PHPCS and PHPStan pass with no errors
10. **Documentation:** Services and REST endpoint are documented

---

## Output Artifacts

After completing this phase, the following will exist:

**New Files:**
```
web/modules/custom/wallet_auth/
├── wallet_auth.install                           # Database installation hook
├── wallet_auth.services.yml                      # Service definitions (updated)
├── wallet_auth.routing.yml                       # REST route (updated)
├── wallet_auth.permissions.yml                   # Permissions (updated)
├── src/
│   ├── Service/
│   │   ├── WalletVerification.php               # Signature verification service
│   │   └── WalletUserManager.php                # User management service
│   └── Controller/
│       └── AuthenticateController.php           # REST endpoint controller
└── config/
    └── install/
        └── wallet_auth.schema.yml               # Database schema definition
```

**Database Tables:**
- `wallet_auth_wallet_address` — Maps wallet addresses to user IDs

**REST Endpoints:**
- `POST /wallet-auth/authenticate` — Authenticate with wallet signature

**Composer Dependencies:**
- `iltumio/siwe-php` — EIP-4361 SIWE implementation (includes web3p/web3.php, kornrunner/keccak, simplito/elliptic-php, nesbot/carbon, guzzlehttp/promises)
- `drupal/externalauth` — External authentication support

---

## Next Steps

After Phase 3 completion, proceed to **Phase 4: Frontend Wallet Integration** which will:

1. Set up NPM build pipeline for JavaScript bundling
2. Install and configure `@human.tech/waap-sdk`
3. Create wallet connection UI components
4. Implement message signing flow
5. Build Drupal block for login button
6. Connect frontend to backend REST endpoint

---

## Notes

- **DDEV Commands:** Always use `ddev` prefix for Composer/Drush/PHP commands
- **Database Access:** Use `ddev exec mysql -uroot -proot db` to access database
- **Service Debugging:** Use `ddev drush debug:container service_name` to verify services
- **Route Debugging:** Use `ddev drush route:debug | grep wallet_auth` to verify routes
- **Logging:** Check Drupal logs: `ddev drush log:watch` or `web/sites/default/files/debug.log`
- **Mode:** Running in "yolo" mode — execute tasks without asking for confirmation

**Security Reminders:**
- NEVER store private keys or seed phrases
- ALWAYS use cryptographically secure random number generation
- ALWAYS validate and sanitize inputs
- NEVER implement custom cryptography — use established libraries
- ALWAYS use prepared statements (Drupal's database API handles this)
- ALWAYS delete nonces after use (prevent replay attacks)

---

*Last updated: 2025-01-12*
