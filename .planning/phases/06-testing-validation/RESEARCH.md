# Research: Phase 6 - Testing & Validation

**Project:** Wallet as a Protocol - Drupal Login Module
**Phase:** 6 - Testing & Validation
**Last Updated:** 2025-01-12

---

## Executive Summary

This phase ensures the wallet authentication module is production-ready through comprehensive testing and validation. The research reveals a clear testing ecosystem for Drupal with well-established patterns for unit, kernel, functional, and browser tests. For Web3 authentication, special attention is needed for signature verification security testing.

---

## 1. Drupal Testing Ecosystem

### 1.1 Test Type Hierarchy

Drupal uses PHPUnit for all automated testing with four distinct test types:

| Test Type | Base Class | Purpose | Speed | Database |
|-----------|------------|---------|-------|----------|
| **Unit** | `UnitTestCase` | Isolated logic testing | Fastest | No |
| **Kernel** | `KernelTestBase` | Service integration, API testing | Fast | Yes (in-memory) |
| **Functional** | `BrowserTestBase` | User workflows via simulated browser | Medium | Yes |
| **FunctionalJavascript** | `WebDriverTestBase` | JavaScript interactions | Slow | Yes |

**Key Insight:** For wallet_auth module, **Kernel tests** are ideal for testing services (WalletVerification, WalletUserManager), while **Functional tests** validate the authentication flow end-to-end.

### 1.2 Test File Structure

```
web/modules/custom/wallet_auth/tests/
├── Kernel/
│   ├── WalletVerificationTest.php       # Signature verification tests
│   ├── WalletUserManagerTest.php        # User creation/linking tests
│   └── SiweMessageParsingTest.php       # SIWE message parsing tests
├── Functional/
│   ├── AuthenticationFlowTest.php       # Full auth flow tests
│   └── WalletLoginBlockTest.php         # Block rendering tests
└── FunctionalJavascript/
    └── WalletConnectionTest.php         # Frontend JS tests (optional)
```

### 1.3 PHPUnit Configuration

Setup requires copying core PHPUnit config:

```bash
cp web/core/phpunit.xml.dist phpunit.xml
```

Run tests from project root:

```bash
# Run all wallet_auth tests
./vendor/bin/phpunit -c phpunit.xml web/modules/custom/wallet_auth/tests

# Run specific test class
./vendor/bin/phpunit -c phpunit.xml web/modules/custom/wallet_auth/tests/Kernel/WalletVerificationTest.php

# Run kernel tests only
./vendor/bin/phpunit -c phpunit.xml --testsuite kernel
```

---

## 2. Critical Testing Areas for Wallet Authentication

### 2.1 Signature Verification (WalletVerification Service)

**Test Coverage Required:**

1. **EIP-191 Signature Verification**
   - Valid signatures with different v values (27, 28, 35+, 0-3)
   - Invalid signatures (wrong signer, tampered message)
   - Malformed signatures (wrong length, invalid hex)

2. **SIWE Message Parsing**
   - Valid SIWE messages with all required fields
   - Messages with optional fields (expirationTime, notBefore)
   - Invalid messages (missing required fields, malformed structure)

3. **Address Validation**
   - Valid checksummed addresses (EIP-55)
   - Valid lowercase addresses
   - Valid uppercase addresses
   - Invalid addresses (wrong length, invalid hex, bad checksum)

4. **Nonce Management**
   - Generation (cryptographically secure, URL-safe base64)
   - Storage and retrieval
   - Expiration (default: 300 seconds)
   - Wallet address matching

5. **Time Validation**
   - Expiration time enforcement
   - Issue time validation with 30-second clock skew tolerance
   - Future-dated messages rejection

**Security Testing Reference:** [SEAL Security Frameworks - Signing & Verification](https://frameworks.securityalliance.org/wallet-security/signing-verification/)

### 2.2 User Management (WalletUserManager Service)

**Test Coverage Required:**

1. **User Creation**
   - New user creation from wallet address
   - Username generation (wallet_ + 8 hex chars)
   - Username collision handling (appends _1, _2, etc.)
   - Email generation (wallet_local@wallet.local)

2. **Wallet-to-User Linking**
   - First-time wallet linking
   - Preventing wallet reassignment to different users
   - Multiple wallets per user
   - Last-used timestamp updates

3. **User Loading**
   - Load existing user by wallet address
   - Handle inactive users
   - Handle missing wallet addresses

### 2.3 REST API (AuthenticateController)

**Test Coverage Required:**

1. **Request Validation**
   - Missing required fields
   - Invalid JSON
   - Invalid address format

2. **Authentication Flow**
   - Successful authentication
   - Invalid signature responses
   - Expired nonce responses
   - Error handling

3. **Response Format**
   - Success response structure
   - Error response structure
   - HTTP status codes

### 2.4 Frontend Integration

**Manual Testing Checklist:**

- [ ] Wallet connection UI renders correctly
- [ ] Auto-connect behavior works (if enabled)
- [ ] Message signing triggers wallet
- [ ] Signature submission completes login
- [ ] Error messages display correctly
- [ ] Multiple network switching works
- [ ] Nonce expiration handling

---

## 3. Security Testing Priorities

### 3.1 Critical Security Checks

Based on recent Drupal security advisories and Web3 best practices:

1. **Signature Replay Prevention**
   - Verify nonce is deleted after use
   - Verify nonce expires after lifetime
   - Test nonce cannot be reused

2. **Address Manipulation**
   - Test address checksum validation
   - Test case-insensitive comparison
   - Test zero-address rejection

3. **Input Validation**
   - XSS prevention in error messages
   - SQL injection prevention in queries
   - Length limits on all inputs

4. **Session Security**
   - Verify `user_login_finalize()` is called
   - Test session fixation prevention
   - Verify secure session flags

**Reference:** [Drupal Security Best Practices](https://www.drupal.org/docs/develop/security)

### 3.2 Web3-Specific Security

From [Quicknode Signature Verification Guide](https://www.quicknode.com/guides/web3-fundamentals-security/cryptography/verify-message-signature-on-ethereum):

1. **EIP-191 Signed Message Prefix**
   - Verify correct prefix: `\x19Ethereum Signed Message:\n`
   - Verify message length is included in hash

2. **Signature Component Extraction**
   - r: 32 bytes
   - s: 32 bytes
   - v: 1 byte (recovery ID)

3. **Recovery ID Normalization**
   - Standard Ethereum: v = 27 + recovery_id
   - EIP-155: v = chainId * 2 + 35 + recovery_id
   - Raw: v = recovery_id (0-3)

---

## 4. Manual Testing Scenarios

### 4.1 End-to-End Authentication Flow

**Scenario: First-Time User Registration**

1. Open Drupal site in fresh browser
2. Navigate to page with Wallet Login block
3. Click "Connect Wallet" button
4. Select wallet provider (MetaMask, WalletConnect, etc.)
5. Approve connection in wallet
6. Review and sign authentication message
7. Verify redirect to logged-in state
8. Verify user account created with wallet_ username
9. Verify wallet address linked in database

**Scenario: Existing User Login**

1. Logout of Drupal
2. Click "Connect Wallet"
3. Connect same wallet as before
4. Sign authentication message
5. Verify login to same user account
6. Verify last_used timestamp updated

### 4.2 Error Handling Scenarios

**Scenario: Expired Nonce**

1. Request nonce from server
2. Wait >5 minutes (nonce_lifetime default)
3. Attempt to sign and authenticate
4. Verify error message displayed
5. Verify new nonce can be requested

**Scenario: Wrong Wallet Signature**

1. Connect Wallet A
2. Request nonce
3. Switch to Wallet B in extension
4. Sign with Wallet B
5. Verify authentication fails
6. Verify clear error message

### 4.3 Configuration Testing

Test admin settings form at `/admin/config/people/wallet-auth`:

| Setting | Values to Test | Expected Behavior |
|---------|----------------|-------------------|
| Network | mainnet, sepolia, polygon, etc. | Changes chain ID in SIWE message |
| Auto-connect | Enabled/Disabled | Auto-opens wallet on page load |
| Nonce Lifetime | 60, 300, 3600 seconds | Adjusts nonce expiration |

---

## 5. Quality Tools & Standards

### 5.1 Static Analysis (Already Configured)

```bash
# PHPCS - Drupal coding standards
./vendor/bin/phpcs web/modules/custom/wallet_auth

# PHPStan - Static analysis (Level 1)
./vendor/bin/phpstan analyse web/modules/custom/wallet_auth
```

**Current Status:** Both tools report 0 errors (from Phase 5).

### 5.2 Test Coverage Reporting

Generate coverage report with PHPUnit:

```bash
./vendor/bin/phpunit -c phpunit.xml --coverage-html coverage/ web/modules/custom/wallet_auth/tests
```

Open `coverage/index.html` to view detailed coverage report.

**Target Metrics:**
- Line Coverage: >80%
- Method Coverage: >90%
- Class Coverage: 100%

### 5.3 Drupal.org Contrib Release Requirements

To prepare for Drupal.org contribution:

1. **Git Repository**
   - Create sandbox project via git.drupal.org
   - Push module with proper branch structure (10.x-1.x)
   - Follow [Drupal Git conventions](https://www.drupal.org/docs/develop/git)

2. **Project Application**
   - Review [Project Application process](https://www.drupal.org/docs/develop/managing-a-drupalorg-theme-module-or-distribution-project/creating-a-new-project/how-to)
   - Ensure all security best practices followed
   - Prepare for PAReview (Project Application Review)

3. **Release Requirements**
   - Stable release tag (e.g., 10.x-1.0-rc1)
   - Complete README.md with installation instructions
   - CHANGELOG.txt documenting changes
   - Proper .info.yml file with dependencies

**References:**
- [Creating a new full project](https://www.drupal.org/docs/develop/managing-a-drupalorg-theme-module-or-distribution-project/creating-a-new-project/how-to)
- [Release naming conventions](https://www.drupal.org/docs/develop/git/git-for-drupal-project-maintainers/release-naming-conventions)

---

## 6. Testing Tools & Dependencies

### 6.1 Required Development Dependencies

Already in `composer.json` (from Phase 1):

```json
{
  "require-dev": {
    "drupal/core-dev": "^10.6"
  }
}
```

This includes:
- PHPUnit
- PHP_CodeSniffer with Drupal standards
- PHPStan
- Drupal core test infrastructure

### 6.2 Optional Browser Testing

For JavaScript interaction testing:

**Option 1: FunctionalJavascript Tests (PHPUnit)**
- Requires ChromeDriver running on port 4444
- Uses WebDriverTestBase
- Slower but tests actual browser

**Option 2: Cypress**
- Modern JavaScript testing framework
- Drupal has [official Cypress docs](https://www.drupal.org/docs/develop/automated-testing/browser-testing-using-cypress)
- Better for complex frontend workflows

**Recommendation:** For Phase 6, skip browser automation tests. Manual testing of the authentication flow is sufficient given the simple nature of the frontend integration.

---

## 7. Common Pitfalls & How to Avoid

### 7.1 Test Isolation Issues

**Problem:** Tests affect database state, causing flaky failures.

**Solution:**
- Kernel tests use in-memory SQLite automatically
- Each test method runs in isolation
- Use `$this->installSchema()` for custom tables
- Use `$this->installConfig()` for module configuration

### 7.2 Time-Dependent Tests

**Problem:** Nonce expiration tests fail depending on system time.

**Solution:**
- Mock TimeInterface service in tests
- Control `$this->time->getRequestTime()` return values
- Test both fresh and expired states explicitly

### 7.3 Cryptographic Testing

**Problem:** Cannot easily test signature verification without real wallet.

**Solution:**
- Use known test vectors from Web3 ecosystem
- Create fixed test accounts with known signatures
- Use elliptic-php to generate test signatures

### 7.4 External Dependencies

**Problem:** Tests fail when externalauth module is not available.

**Solution:**
- Add externalauth to `require` section (not require-dev)
- Mock ExternalAuthInterface in unit/kernel tests where appropriate
- Ensure test database has required modules enabled

---

## 8. Recommended Test Implementation Order

### Phase 6A: Kernel Tests (High Priority)
1. SiweMessageParsingTest - Parse SIWE messages
2. WalletVerificationTest - Signature verification logic
3. WalletUserManagerTest - User creation and linking

### Phase 6B: Functional Tests (High Priority)
1. AuthenticationFlowTest - Full REST API flow
2. WalletLoginBlockTest - Block rendering and configuration
3. SettingsFormTest - Admin form submission

### Phase 6C: Manual Testing (Critical)
1. Fresh install authentication flow
2. Existing user login
3. Error scenarios (expired nonce, wrong signature)
4. Configuration form testing

### Phase 6D: Security Review (Critical)
1. Code review against OWASP top 10
2. Web3-specific security review
3. Input validation audit
4. Session security verification

### Phase 6E: Release Preparation
1. Update README with testing section
2. Create CHANGELOG.txt
3. Verify .info.yml completeness
4. Run full test suite one final time

---

## 9. Success Criteria

Phase 6 is complete when:

- [ ] All kernel tests pass (services)
- [ ] All functional tests pass (workflows)
- [ ] Manual E2E testing completed successfully
- [ ] Security checklist completed
- [ ] Code coverage >80% for critical paths
- [ ] Documentation updated (README, CHANGELOG)
- [ ] Module tested on fresh Drupal install
- [ ] Ready for Drupal.org sandbox release

---

## 10. References & Further Reading

### Drupal Testing
- [Drupal Automated Testing Overview](https://www.drupal.org/docs/develop/automated-testing)
- [Types of Tests in Drupal](https://www.drupal.org/docs/develop/automated-testing/types-of-tests)
- [Write a Kernel Test](https://drupalize.me/tutorial/write-kernel-test) (Drupalize.Me)
- [Write a Functional Test](https://drupalize.me/tutorial/write-functional-test) (Drupalize.Me)

### Web3 Security
- [SEAL Security Frameworks - Signing & Verification](https://frameworks.securityalliance.org/wallet-security/signing-verification/)
- [Understanding Ethereum Signatures](https://www.kayssel.com/post/web3-19/)
- [EIP-712 and EIP-191 Explained](https://www.cyfrin.io/blog/understanding-ethereum-signature-standards-eip-191-eip-712)
- [Sign-In with Ethereum Research](https://blog.spruceid.com/sign-in-with-ethereum-wallet-support-eip-191-vs-eip-712/)

### Drupal Security
- [Drupal Security Documentation](https://www.drupal.org/docs/develop/security)
- [HMAC Best Practices](https://www.drupal.org/docs/develop/security/hmac-best-practices)
- [Security Kit Module](https://www.drupal.org/project/seckit) (for reference)

### Module Release
- [Creating a New Drupal Project](https://www.drupal.org/docs/develop/managing-a-drupalorg-theme-module-or-distribution-project/creating-a-new-project/how-to)
- [Git for Drupal Project Maintainers](https://www.drupal.org/docs/develop/git/git-for-drupal-project-maintainers)
- [Release Naming Conventions](https://www.drupal.org/docs/develop/git/git-for-drupal-project-maintainers/release-naming-conventions)

---

**Summary:** The research reveals a well-established testing ecosystem in Drupal. For the wallet_auth module, focus on kernel tests for service logic and functional tests for the authentication workflow. Web3 signature verification requires special attention to test vectors and security scenarios. Manual E2E testing is critical for the wallet connection flow.
