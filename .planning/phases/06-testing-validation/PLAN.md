# Plan: Phase 6 - Testing & Validation

**Project:** Wallet as a Protocol - Drupal Login Module
**Phase:** 6 - Testing & Validation
**Last Updated:** 2025-01-12
**Mode:** yolo

---

## Objective

Ensure the wallet authentication module is production-ready through comprehensive testing, security review, and validation. This phase covers automated tests (PHPUnit), manual end-to-end testing, security audit, and preparation for Drupal.org contrib release.

---

## Execution Context

**Phase Type:** Testing & Validation

**Scope:**
- Create PHPUnit test suite (kernel + functional tests)
- Manual end-to-end testing of authentication flow
- Security review against Web3 and Drupal best practices
- Documentation updates for release readiness
- Prepare for Drupal.org sandbox submission

**Dependencies:**
- Phase 5 must be complete (configuration, admin settings, code quality)
- DDEV environment running
- PHPUnit configured (copy from core)

**Estimated Complexity:** Medium - Well-established testing patterns in Drupal

---

## Context

### Current State

From STATE.md:
- **Phase 1-5:** Complete (100%)
- **Code Quality:** PHPCS (0 errors), PHPStan (0 errors)
- **Services Implemented:**
  - `WalletVerification` - EIP-191 signature verification, SIWE parsing, nonce management
  - `WalletUserManager` - User creation/linking via ExternalAuth
  - `AuthenticateController` - REST API endpoint
  - `NonceController` - Nonce generation endpoint
  - `SettingsForm` - Admin configuration

### Module Structure

```
web/modules/custom/wallet_auth/
├── src/
│   ├── Service/
│   │   ├── WalletVerification.php      (555 lines) - SIWE verification, EIP-191
│   │   └── WalletUserManager.php       (320 lines) - User CRUD, wallet linking
│   ├── Controller/
│   │   ├── AuthenticateController.php  (145 lines) - POST /wallet-auth/authenticate
│   │   └── NonceController.php         - GET /wallet-auth/nonce
│   ├── Form/
│   │   └── SettingsForm.php            - Admin config form
│   └── Plugin/Block/
│       └── WalletLoginBlock.php        - Frontend block
├── templates/
│   └── wallet-login-button.html.twig
├── wallet_auth.install                  - Database schema
├── wallet_auth.services.yml             - Service definitions
└── wallet_auth.routing.yml              - REST routes
```

### Key Decisions from Prior Phases

From ADR-003 (SIWE Implementation):
- Manual SIWE implementation using `kornrunner/keccak` + `simplito/elliptic-php`
- EIP-191 `personal_sign` for signature verification
- Private tempstore for nonce storage (5-minute expiry)

From ADR-004 (User Management):
- One-to-many wallet-to-user mapping
- Username format: `wallet_` + 8 hex chars
- ExternalAuth module for user management

### Testing Requirements from Research

From `.planning/phases/06-testing-validation/RESEARCH.md`:

**Test Types Needed:**
1. **Kernel Tests** - Service logic (WalletVerification, WalletUserManager)
2. **Functional Tests** - REST API, authentication flow, admin forms
3. **Manual Testing** - End-to-end wallet connection flow
4. **Security Review** - Signature verification, input validation, session security

**Critical Test Areas:**
- Signature verification (valid/invalid signatures, v-value normalization)
- SIWE message parsing (required fields, time validation)
- Nonce management (generation, expiration, replay prevention)
- User creation (username collision handling, wallet linking)
- REST API (request validation, error responses)

---

## Tasks

### Task 1: Setup PHPUnit Configuration

Configure PHPUnit for the module and verify test infrastructure works.

**Steps:**
1. Copy PHPUnit config from Drupal core: `cp web/core/phpunit.xml.dist phpunit.xml`
2. Verify `SIMPLE_TEST` config in `phpunit.xml` (points to test database)
3. Create test directory structure:
   ```
   tests/
   ├── Kernel/
   ├── Functional/
   └── FunctionalJavascript/ (optional)
   ```
4. Verify tests can be discovered: `./vendor/bin/phpunit -c phpunit.xml --list-testsuites`

**Acceptance Criteria:**
- PHPUnit configuration file exists in project root
- Test directory structure created
- `phpunit.xml` configured for SQLite test database

**File Changes:**
- Create: `phpunit.xml`
- Create: `web/modules/custom/wallet_auth/tests/Kernel/.gitkeep`
- Create: `web/modules/custom/wallet_auth/tests/Functional/.gitkeep`

---

### Task 2: Create Kernel Tests for WalletVerification

Test signature verification, SIWE parsing, address validation, and nonce management.

**Test File:** `tests/Kernel/WalletVerificationTest.php`

**Test Cases:**

1. **Nonce Management**
   - `testGenerateNonce()` - Returns 32-byte random string, base64url encoded
   - `testStoreAndRetrieveNonce()` - Stores and retrieves with wallet address binding
   - `testNonceExpiration()` - Nonce expires after NONCE_LIFETIME seconds
   - `testNonceWalletMismatch()` - Fails when wallet address doesn't match

2. **Address Validation**
   - `testValidChecksummedAddress()` - Accepts EIP-55 checksummed address
   - `testValidLowercaseAddress()` - Accepts lowercase address
   - `testValidUppercaseAddress()` - Accepts uppercase address
   - `testInvalidAddressMissingPrefix()` - Rejects address without 0x prefix
   - `testInvalidAddressWrongLength()` - Rejects addresses not 42 chars
   - `testInvalidAddressBadHex()` - Rejects addresses with non-hex chars
   - `testInvalidAddressBadChecksum()` - Rejects invalid EIP-55 checksum

3. **SIWE Message Parsing**
   - `testParseValidSiweMessage()` - Parses all required fields correctly
   - `testParseSiweWithOptionalFields()` - Handles expirationTime, notBefore
   - `testParseSiweMissingRequiredField()` - Returns NULL for missing domain/address/uri/version/nonce/issuedAt
   - `testParseSiweInvalidAddress()` - Returns NULL for invalid address
   - `testParseSiweWithResources()` - Parses multi-line resources array

4. **Signature Verification** (with known test vectors)
   - `testVerifyValidSignature()` - Accepts valid signature from known test vector
   - `testVerifyInvalidSignature()` - Rejects signature from wrong signer
   - `testVerifySignatureAddressMismatch()` - Rejects when recovered address doesn't match
   - `testVerifySignatureExpiredMessage()` - Rejects expired SIWE message
   - `testVerifySignatureFutureMessage()` - Rejects future-dated message (>30s skew)
   - `testVerifySignatureInvalidNonce()` - Rejects when nonce not found/expired
   - `testVerifySignatureEVNormalization()` - Handles v = 27, 28, 35+, 0-3

**Acceptance Criteria:**
- All tests pass: `./vendor/bin/phpunit -c phpunit.xml web/modules/custom/wallet_auth/tests/Kernel/WalletVerificationTest.php`
- Code coverage >80% for WalletVerification service
- Test vectors use known Ethereum signatures (e.g., from web3.js or eth-account tests)

**File Changes:**
- Create: `tests/Kernel/WalletVerificationTest.php` (~300 lines)

---

### Task 3: Create Kernel Tests for WalletUserManager

Test user creation, wallet linking, and username generation.

**Test File:** `tests/Kernel/WalletUserManagerTest.php`

**Test Cases:**

1. **Username Generation**
   - `testGenerateUsernameFromWallet()` - Generates `wallet_` + 8 hex chars
   - `testGenerateUsernameCollision()` - Appends _1, _2 for collisions
   - `testGenerateUsernameUnique()` - Ensures generated username doesn't exist

2. **User Creation**
   - `testCreateUserFromWallet()` - Creates new user with wallet_ username
   - `testCreateUserEmailGeneration()` - Sets email to `username@wallet.local`
   - `testCreateUserWalletLinking()` - Links wallet to created user
   - `testCreateUserActivation()` - User is active (not blocked)

3. **Wallet Linking**
   - `testLinkWalletToUser()` - Inserts new wallet address mapping
   - `testLinkWalletToExistingUser()` - Updates last_used timestamp
   - `testLinkWalletToDifferentUserRejected()` - Prevents reassigning wallet to different user
   - `testLinkMultipleWalletsToOneUser()` - Allows multiple wallets per user

4. **User Loading**
   - `testLoadUserByWalletAddress()` - Returns user for existing wallet
   - `testLoadUserByWalletAddressNotFound()` - Returns NULL for unknown wallet
   - `testLoadUserByInactiveUser()` - Returns NULL for blocked user
   - `testGetUserWallets()` - Returns array of wallet addresses for user

5. **Authentication Flow**
   - `testLoginOrCreateUserNewUser()` - Creates new user on first auth
   - `testLoginOrCreateUserExistingUser()` - Returns existing user on subsequent auth
   - `testLoginOrCreateUserUpdatesLastUsed()` - Updates last_used timestamp

**Acceptance Criteria:**
- All tests pass
- Code coverage >80% for WalletUserManager service
- Tests use proper database setup (installSchema, installEntitySchema)

**File Changes:**
- Create: `tests/Kernel/WalletUserManagerTest.php` (~250 lines)

---

### Task 4: Create Functional Tests for REST API

Test the authentication REST endpoint with simulated browser.

**Test File:** `tests/Functional/AuthenticationFlowTest.php`

**Test Cases:**

1. **Request Validation**
   - `testAuthenticateMissingFields()` - Returns 400 for missing wallet_address/signature/message
   - `testAuthenticateInvalidJson()` - Returns 400 for malformed JSON
   - `testAuthenticateInvalidAddress()` - Returns 400 for invalid address format

2. **Authentication Flow**
   - `testAuthenticateNewUser()` - Creates new user, returns success
   - `testAuthenticateExistingUser()` - Logs in existing user, returns success
   - `testAuthenticateInvalidSignature()` - Returns 401 for invalid signature
   - `testAuthenticateExpiredNonce()` - Returns 401 for expired nonce
   - `testAuthenticateNonceDeletedAfterUse()` - Nonce cannot be reused

3. **Response Format**
   - `testAuthenticateSuccessResponse()` - Returns {success: true, uid, username}
   - `testAuthenticateErrorResponse()` - Returns {success: false, error}

4. **Session Management**
   - `testAuthenticateLogsInUser()` - User is logged in after successful auth (verify session)
   - `testAuthenticateCallsUserLoginFinalize()` - Triggers proper Drupal login hooks

**Acceptance Criteria:**
- All tests pass
- Tests use BrowserTestBase for simulated HTTP requests
- Verifies both response format and Drupal session state

**File Changes:**
- Create: `tests/Functional/AuthenticationFlowTest.php` (~200 lines)

---

### Task 5: Create Functional Tests for Block and Settings

Test the WalletLoginBlock and admin settings form.

**Test File:** `tests/Functional/WalletLoginBlockTest.php`

**Test Cases:**

1. **Block Rendering**
   - `testBlockRender()` - Block renders on page
   - `testBlockContainsDrupalSettings()` - drupalSettings includes network, autoConnect
   - `testBlockConfiguration()` - Block is configurable

**Test File:** `tests/Functional/SettingsFormTest.php`

**Test Cases:**

1. **Settings Form Access**
   - `testSettingsFormAccess()` - Admin can access form at /admin/config/people/wallet-auth
   - `testSettingsFormAccessDenied()` - Non-admin cannot access

2. **Settings Form Submission**
   - `testSaveNetworkConfiguration()` - Saves network setting
   - `testSaveAutoConnectConfiguration()` - Saves auto-connect setting
   - `testSaveNonceLifetimeConfiguration()` - Saves nonce_lifetime (60-3600 range)
   - `testNonceLifetimeValidation()` - Rejects values outside 60-3600 range

**Acceptance Criteria:**
- All tests pass
- Tests verify both UI rendering and configuration persistence

**File Changes:**
- Create: `tests/Functional/WalletLoginBlockTest.php` (~100 lines)
- Create: `tests/Functional/SettingsFormTest.php` (~150 lines)

---

### Task 6: Manual End-to-End Testing

Perform manual testing of the complete authentication flow with a real wallet.

**Test Scenarios:**

1. **First-Time User Registration**
   - [ ] Fresh Drupal install (ensure wallet_auth module enabled)
   - [ ] Place Wallet Login block on homepage
   - [ ] Click "Connect Wallet" button
   - [ ] Select MetaMask (or other wallet)
   - [ ] Approve connection request
   - [ ] Review SIWE message in wallet
   - [ ] Sign message
   - [ ] Verify redirect to logged-in state
   - [ ] Verify user created: username = `wallet_<address>`, email = `@wallet.local`
   - [ ] Check database: wallet_auth_wallet_address table has entry

2. **Existing User Login**
   - [ ] Logout of Drupal
   - [ ] Click "Connect Wallet" again
   - [ ] Connect same wallet address
   - [ ] Sign new SIWE message
   - [ ] Verify login to same user account (not duplicate)
   - [ ] Check database: last_used timestamp updated

3. **Error Handling**
   - [ ] Request nonce, wait >5 minutes, attempt auth → verify expired nonce error
   - [ ] Connect Wallet A, request nonce, switch to Wallet B, sign with B → verify signature mismatch error
   - [ ] Disconnect wallet mid-flow → verify error message

4. **Configuration Testing**
   - [ ] Navigate to /admin/config/people/wallet-auth
   - [ ] Change network to sepolia → verify chain ID in SIWE message
   - [ ] Enable auto-connect → verify wallet opens on page load
   - [ ] Set nonce_lifetime to 60 seconds → verify nonce expires after 1 minute

5. **Multiple Networks**
   - [ ] Test with Ethereum mainnet
   - [ ] Test with Polygon
   - [ ] Test with BSC
   - [ ] Verify chain ID in SIWE message matches configured network

**Acceptance Criteria:**
- All manual test scenarios documented with results
- Screenshot/notes for each scenario
- Any bugs discovered are documented and fixed

**File Changes:**
- Create: `.planning/phases/06-testing-validation/MANUAL_TEST_RESULTS.md`

---

### Task 7: Security Review

Perform security audit against OWASP Top 10 and Web3 best practices.

**Security Checklist:**

1. **Input Validation**
   - [ ] All user inputs validated and sanitized
   - [ ] SQL injection prevention (use parameterized queries)
   - [ ] XSS prevention (output escaping in Twig, no raw HTML in JS)
   - [ ] CSRF protection (Drupal core handles for forms)
   - [ ] Length limits on all inputs (address, signature, message)

2. **Signature Verification**
   - [ ] Nonce deleted after use (replay attack prevention)
   - [ ] Nonce expires after lifetime
   - [ ] Address checksum validation (EIP-55)
   - [ ] Signature recovery verifies correct signer
   - [ ] SIWE message expiration time enforced
   - [ ] SIWE message not issued in future (clock skew tolerance)

3. **Session Security**
   - [ ] `user_login_finalize()` called (session fixation prevention)
   - [ ] Secure session flags (httpOnly, sameSite)
   - [ ] Proper logout handling

4. **Web3-Specific Security**
   - [ ] EIP-191 prefix correctly applied
   - [ ] Message length included in hash
   - [ ] v-value normalization handles all formats (27, 28, 35+, 0-3)
   - [ ] Reject zero address (0x0000...)
   - [ ] Case-insensitive address comparison

5. **Logging & Error Handling**
   - [ ] Sensitive data not logged (private keys, full signatures)
   - [ ] Error messages don't leak implementation details
   - [ ] Exceptions caught and handled gracefully

**Acceptance Criteria:**
- All security checklist items verified
- Any vulnerabilities documented and fixed
- Security review summary created

**File Changes:**
- Create: `.planning/phases/06-testing-validation/SECURITY_REVIEW.md`

---

### Task 8: Code Coverage Analysis

Generate and review code coverage report to ensure comprehensive test coverage.

**Steps:**
1. Generate coverage report:
   ```bash
   ./vendor/bin/phpunit -c phpunit.xml --coverage-html .planning/phases/06-testing-validation/coverage web/modules/custom/wallet_auth/tests
   ```
2. Open coverage report: `.planning/phases/06-testing-validation/coverage/index.html`
3. Review coverage for:
   - WalletVerification service (target: >80%)
   - WalletUserManager service (target: >80%)
   - AuthenticateController (target: >70%)
   - SettingsForm (target: >60%)
4. Identify any untested code paths
5. Add tests for uncovered critical paths

**Acceptance Criteria:**
- Coverage report generated
- Critical services have >80% coverage
- Uncovered code paths reviewed and documented

**File Changes:**
- Create: `.planning/phases/06-testing-validation/CODE_COVERAGE_SUMMARY.md`

---

### Task 9: Update Documentation for Release

Prepare documentation for Drupal.org contrib release.

**Documentation Updates:**

1. **README.md** - Add sections:
   - Testing (how to run tests)
   - Security considerations
   - Troubleshooting (expanded)
   - Contributing guidelines
   - License (GPL-2.0-or-later)

2. **CHANGELOG.txt** - Create changelog:
   ```text
   Wallet Auth 10.x-1.0 (2025-01-12)
   ------------------------
   - Initial release
   - Wallet-based authentication using Sign-In with Ethereum (EIP-4361)
   - EIP-191 signature verification
   - Support for multiple networks (Ethereum, Polygon, BSC, Arbitrum, Optimism)
   - Admin configuration interface
   - Wallet login block for Drupal
   - External Auth integration
   ```

3. **wallet_auth.info.yml** - Add:
   - `dependencies: { drupal:externalauth }`
   - `configure: wallet_auth.settings`
   - Proper PHP version (`php: 8.2 or higher`)

**Acceptance Criteria:**
- README.md includes all sections
- CHANGELOG.txt created with proper format
- .info.yml file complete with dependencies

**File Changes:**
- Edit: `README.md`
- Create: `web/modules/custom/wallet_auth/CHANGELOG.txt`
- Edit: `web/modules/custom/wallet_auth/wallet_auth.info.yml`

---

### Task 10: Final Quality Checks

Run final quality checks to ensure release readiness.

**Steps:**

1. **Code Quality**
   ```bash
   # PHPCS
   ./vendor/bin/phpcs web/modules/custom/wallet_auth --standard=Drupal
   ./vendor/bin/phpcs web/modules/custom/wallet_auth --standard=DrupalPractice

   # PHPStan
   ./vendor/bin/phpstan analyse web/modules/custom/wallet_auth --level=1

   # PHPUnit (all tests)
   ./vendor/bin/phpunit -c phpunit.xml web/modules/custom/wallet_auth/tests
   ```

2. **Module Status**
   ```bash
   # DDEV
   ddev drush status
   ddev drush pm:list --type=module --status=enabled | grep wallet_auth
   ```

3. **Cache Clear**
   ```bash
   ddev drush cache:rebuild
   ddev drush cron
   ```

4. **Manual Smoke Test**
   - [ ] Fresh install works
   - [ ] Module enables without errors
   - [ ] Settings form saves
   - [ ] Block can be placed
   - [ ] Wallet connection works

**Acceptance Criteria:**
- All quality checks pass (0 errors)
- Module works on fresh Drupal install
- Ready for Drupal.org sandbox submission

**File Changes:**
- Create: `.planning/phases/06-testing-validation/FINAL_CHECKLIST.md`

---

### Task 11: Archive Phase and Update State

Archive completed phase and prepare for next steps.

**Steps:**

1. **Create Phase Summary**
   - Document all tests created
   - Document test results (all passing)
   - Document security review findings
   - List any issues found and fixed

2. **Update STATE.md**
   - Mark Phase 6 as complete
   - Add session history for Phase 6
   - Update current phase to "Complete"

3. **Commit Phase Deliverables**
   ```bash
   git add .
   git commit -m "feat(06-testing-validation): complete testing and validation

   - Created PHPUnit test suite (kernel + functional tests)
   - Kernel tests for WalletVerification (signature verification, SIWE parsing)
   - Kernel tests for WalletUserManager (user creation, wallet linking)
   - Functional tests for REST API authentication flow
   - Functional tests for block and admin settings
   - Manual E2E testing completed successfully
   - Security review passed (OWASP + Web3)
   - Code coverage >80% for critical services
   - Documentation updated for contrib release
   "
   ```

**Acceptance Criteria:**
- Phase summary created
- STATE.md updated
- All changes committed

**File Changes:**
- Create: `.planning/phases/06-testing-validation/SUMMARY.md`
- Edit: `.planning/STATE.md`
- Edit: `.planning/ROADMAP.md` (update Phase 6 status)

---

## Verification

### Completion Checklist

- [ ] PHPUnit configured and tests run successfully
- [ ] Kernel tests created for WalletVerification (all passing)
- [ ] Kernel tests created for WalletUserManager (all passing)
- [ ] Functional tests created for REST API (all passing)
- [ ] Functional tests created for block/settings (all passing)
- [ ] Manual E2E testing completed with documented results
- [ ] Security review completed and documented
- [ ] Code coverage >80% for critical services
- [ ] Documentation updated (README, CHANGELOG, .info.yml)
- [ ] All quality checks pass (PHPCS, PHPStan, PHPUnit)
- [ ] Module tested on fresh Drupal install
- [ ] Phase summary created and state updated

### Success Criteria

Phase 6 is complete when:

1. **All automated tests pass** - PHPUnit test suite with 100% pass rate
2. **Code coverage threshold met** - >80% coverage for WalletVerification and WalletUserManager
3. **Manual testing successful** - E2E authentication flow works end-to-end
4. **Security review passed** - No critical vulnerabilities, all Web3 best practices followed
5. **Documentation complete** - README, CHANGELOG, .info.yml ready for contrib release
6. **Quality verified** - PHPCS (0 errors), PHPStan (0 errors)
7. **Production-ready** - Module can be safely deployed to production

### Output Artifacts

- `tests/Kernel/WalletVerificationTest.php` - Signature verification tests
- `tests/Kernel/WalletUserManagerTest.php` - User management tests
- `tests/Functional/AuthenticationFlowTest.php` - REST API tests
- `tests/Functional/WalletLoginBlockTest.php` - Block tests
- `tests/Functional/SettingsFormTest.php` - Admin form tests
- `.planning/phases/06-testing-validation/MANUAL_TEST_RESULTS.md` - E2E test results
- `.planning/phases/06-testing-validation/SECURITY_REVIEW.md` - Security audit
- `.planning/phases/06-testing-validation/CODE_COVERAGE_SUMMARY.md` - Coverage report
- `phpunit.xml` - PHPUnit configuration
- `web/modules/custom/wallet_auth/CHANGELOG.txt` - Release changelog
- `.planning/phases/06-testing-validation/SUMMARY.md` - Phase summary
- Updated `.planning/STATE.md` and `.planning/ROADMAP.md`

---

## Next Steps

After Phase 6 completion:

1. **Drupal.org Sandbox** - Create git.drupal.org sandbox project
2. **Project Application** - Submit full project application for review
3. **PAReview** - Address feedback from Drupal community review
4. **Stable Release** - Tag 10.x-1.0 release
5. **Maintenance** - Address issues, add features based on user feedback

---

**Ready to execute?** Run `/gsd:execute-plan` to begin Phase 6 implementation.
