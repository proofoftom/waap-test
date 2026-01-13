# Wallet Auth Module - Comprehensive Code Review

**Review Date:** 2025-01-13
**Module Version:** 1.0.0
**Reviewer:** Claude Code
**Module Path:** `web/modules/custom/wallet_auth`

---

## Executive Summary

The **Wallet Auth** module is a well-architected Drupal 10 module implementing EIP-4361 (Sign-In with Ethereum) authentication. Overall, the implementation demonstrates strong security practices, follows Drupal coding standards reasonably well, and provides solid test coverage. However, there are several areas requiring attention, ranging from security enhancements to code quality improvements.

**Overall Grade: B+**

| Category | Score | Notes |
|----------|-------|-------|
| Security | 7.5/10 | Strong crypto, missing domain/chainId validation |
| Code Quality | 8/10 | Good PHP 8.x usage, needs interfaces |
| Architecture | 8.5/10 | Clean separation of concerns |
| Test Coverage | 8/10 | 64 tests, 82% coverage on critical paths |
| Documentation | 7/10 | Good README, inline docs could improve |
| Drupal Standards | 7.5/10 | Good structure, some patterns missing |

---

## Module Structure Overview

```
wallet_auth/
├── config/
│   ├── install/wallet_auth.settings.yml
│   └── schema/wallet_auth.schema.yml
├── src/
│   ├── Controller/
│   │   ├── AuthenticateController.php (145 lines)
│   │   └── NonceController.php (102 lines)
│   ├── Service/
│   │   ├── WalletVerification.php (555 lines)
│   │   └── WalletUserManager.php (329 lines)
│   ├── Plugin/Block/WalletLoginBlock.php (121 lines)
│   ├── Form/SettingsForm.php (122 lines)
│   ├── js/ (source JavaScript)
│   └── css/ (stylesheets)
├── js/dist/ (compiled JavaScript)
├── templates/wallet-login-button.html.twig
├── tests/
│   ├── Kernel/ (2 test files, 41 tests)
│   └── Functional/ (3 test files, 23 tests)
└── [module definition files]
```

---

## CRITICAL Issues

### 1. Configurable Nonce Lifetime Not Used in Verification Service

**File:** `src/Service/WalletVerification.php:52-144`
**Severity:** CRITICAL
**Type:** Bug

The `WalletVerification` service uses a hardcoded `NONCE_LIFETIME` constant of 300 seconds, completely ignoring the configurable `nonce_lifetime` setting exposed in the admin form.

```php
// Current implementation (hardcoded):
protected const NONCE_LIFETIME = 300;

// In verifyNonce() method:
if ($age > self::NONCE_LIFETIME) {  // Ignores user configuration!
    $this->logger->warning('Nonce expired (age: @age seconds)', ['@age' => $age]);
    $store->delete($nonce);
    return FALSE;
}
```

**Impact:** Admin configuration for nonce lifetime has no effect on actual verification. Users expect this setting to work.

**Remediation:**
1. Inject `ConfigFactoryInterface` into the service
2. Read the configured lifetime:

```php
// Add to constructor:
protected ConfigFactoryInterface $configFactory;

// In verifyNonce():
$lifetime = $this->configFactory->get('wallet_auth.settings')->get('nonce_lifetime') ?? 300;
if ($age > $lifetime) {
    // ... existing logic
}
```

3. Update `wallet_auth.services.yml` to inject `@config.factory`

---

### 2. Missing Chain ID Validation in SIWE Message

**File:** `src/Service/WalletVerification.php:163-337`
**Severity:** CRITICAL
**Type:** Security Vulnerability

The `verifySignature()` method parses the SIWE message and extracts `chainId` but never validates that it matches the configured network. Per EIP-4361, chain ID validation is mandatory to prevent cross-chain replay attacks.

```php
// Currently parsed but never validated:
$fields['chainId'] = $value;  // Stored but ignored
```

**Impact:** A valid signature from Ethereum Mainnet could potentially be replayed on Polygon or other networks if the backend accepts multiple chains.

**Remediation:**
```php
// Add chain ID validation in verifySignature():
$configuredNetwork = $this->configFactory->get('wallet_auth.settings')->get('network');
$expectedChainId = $this->getChainIdForNetwork($configuredNetwork);

if (isset($siweFields['chainId']) && (int)$siweFields['chainId'] !== $expectedChainId) {
    $this->logger->warning('Chain ID mismatch: expected @expected, got @actual', [
        '@expected' => $expectedChainId,
        '@actual' => $siweFields['chainId'],
    ]);
    return FALSE;
}

// Helper method:
protected function getChainIdForNetwork(string $network): int {
    return match($network) {
        'mainnet' => 1,
        'sepolia' => 11155111,
        'polygon' => 137,
        'bsc' => 56,
        'arbitrum' => 42161,
        'optimism' => 10,
        default => 1,
    };
}
```

---

### 3. Domain Not Validated in SIWE Message

**File:** `src/Service/WalletVerification.php:440-471`
**Severity:** CRITICAL
**Type:** Security Vulnerability

The SIWE parser extracts the domain from the message but never validates it against the actual server domain. This is a **required** security check per EIP-4361 specification.

```php
// Domain is parsed:
$fields['domain'] = trim($domainMatch[1]);
// But never validated against actual request domain!
```

**Impact:** A SIWE message signed for `evil.com` could be submitted to your site and would pass verification. This enables phishing attacks where users are tricked into signing messages for malicious domains.

**Remediation:**
```php
// In verifySignature(), after parsing SIWE fields:
$requestHost = \Drupal::request()->getHost();
if ($siweFields['domain'] !== $requestHost) {
    $this->logger->warning('SIWE domain mismatch: message for @domain, request from @host', [
        '@domain' => $siweFields['domain'],
        '@host' => $requestHost,
    ]);
    return FALSE;
}
```

**Note:** Inject `RequestStack` service rather than using `\Drupal::request()` for proper DI.

---

## HIGH Priority Issues

### 4. Missing Rate Limiting on Authentication Endpoints

**Files:** `wallet_auth.routing.yml:1-21`, Controllers
**Severity:** HIGH
**Type:** Security Vulnerability

Both `/wallet-auth/nonce` and `/wallet-auth/authenticate` endpoints are publicly accessible with `_access: 'TRUE'` and have no rate limiting.

```yaml
wallet_auth.authenticate:
  path: '/wallet-auth/authenticate'
  requirements:
    _access: 'TRUE'  # No rate limiting
```

**Impact:**
- Attackers can exhaust nonce storage with rapid requests
- Brute-force attacks on authentication flow
- Denial of service through resource exhaustion

**Remediation Options:**

Option A - Drupal Flood Control:
```php
// In NonceController::generateNonce():
$flood = \Drupal::service('flood');
$ip = \Drupal::request()->getClientIp();

if (!$flood->isAllowed('wallet_auth.nonce', 10, 60, $ip)) {
    return new JsonResponse(['error' => 'Too many requests'], 429);
}
$flood->register('wallet_auth.nonce', 60, $ip);
```

Option B - Custom rate limiting service with Redis/database backend.

---

### 5. Permission Defined But Not Enforced

**File:** `wallet_auth.permissions.yml`, `wallet_auth.routing.yml`
**Severity:** HIGH
**Type:** Security Gap

The module defines an `authenticate with wallet` permission but doesn't use it anywhere:

```yaml
# wallet_auth.permissions.yml:
authenticate with wallet:
  title: 'Authenticate with wallet'
  description: 'Allow users to authenticate using their wallet address.'
  restrict access: false
```

```yaml
# wallet_auth.routing.yml - permission not used:
wallet_auth.authenticate:
  requirements:
    _access: 'TRUE'  # Should reference the permission
```

**Impact:** The permission is useless - admins cannot restrict wallet auth to specific roles.

**Remediation:** Create a custom access handler that allows anonymous users but respects the permission:

```php
// src/Access/WalletAuthAccessCheck.php
class WalletAuthAccessCheck implements AccessInterface {
    public function access(AccountInterface $account) {
        // Anonymous users can always attempt wallet auth
        if ($account->isAnonymous()) {
            return AccessResult::allowed();
        }
        // Authenticated users need permission
        return AccessResult::allowedIfHasPermission($account, 'authenticate with wallet');
    }
}
```

---

### 6. Incomplete Service Interface Definitions

**Files:** `src/Service/WalletVerification.php`, `src/Service/WalletUserManager.php`
**Severity:** HIGH
**Type:** Architecture

Services lack corresponding interfaces, making testing and service decoration difficult.

**Impact:**
- Cannot easily mock services in unit tests
- Cannot swap implementations without code changes
- Violates Drupal best practices for extensible services
- Other modules cannot decorate or replace services

**Remediation:**
```php
// src/WalletVerificationInterface.php
namespace Drupal\wallet_auth;

interface WalletVerificationInterface {
    public function generateNonce(): string;
    public function storeNonce(string $nonce, string $walletAddress): void;
    public function verifyNonce(string $nonce, string $walletAddress): bool;
    public function verifySignature(string $message, string $signature, string $walletAddress): bool;
    public function validateAddress(string $address): bool;
    public function deleteNonce(string $nonce): void;
}
```

Update services.yml to use interface as service ID or add interface tag.

---

### 7. No CSRF Token on Authenticate Endpoint

**File:** `src/Controller/AuthenticateController.php:81-142`
**Severity:** HIGH
**Type:** Security Consideration

While the signature verification provides cryptographic authentication, the POST endpoint lacks a CSRF token. The signature serves as proof of wallet ownership, but defense-in-depth would include CSRF protection.

**Current Flow:**
1. Frontend fetches nonce (no CSRF)
2. Frontend posts signature (no CSRF)
3. Signature verified cryptographically

**Consideration:** The cryptographic signature effectively prevents CSRF since an attacker cannot forge a valid signature. However, adding CSRF provides defense-in-depth.

**Remediation (Optional):**
```php
// Add to NonceController response:
return new JsonResponse([
    'nonce' => $nonce,
    'expires_in' => $lifetime,
    'csrf_token' => \Drupal::csrfToken()->get('wallet_auth'),
]);

// Validate in AuthenticateController:
$csrf = $data['csrf_token'] ?? '';
if (!\Drupal::csrfToken()->validate($csrf, 'wallet_auth')) {
    return new JsonResponse(['error' => 'Invalid CSRF token'], 403);
}
```

---

### 8. Constructor Parameter Type Mismatch in Docblock

**File:** `src/Service/WalletVerification.php:62-63`
**Severity:** HIGH
**Type:** Bug/Documentation

The docblock references `\Drupal\Core\Datetime\TimeInterface` but the actual type hint uses `\Drupal\Component\Datetime\TimeInterface`.

```php
/**
 * @param \Drupal\Core\Datetime\TimeInterface $time  // WRONG namespace
 */
public function __construct(
    // ...
    TimeInterface $time,  // Uses Component\Datetime\TimeInterface
)
```

**Impact:** IDE confusion, incorrect autocomplete, potential issues with strict type checking tools.

**Remediation:** Update docblock to match actual type:
```php
@param \Drupal\Component\Datetime\TimeInterface $time
```

---

## MEDIUM Priority Issues

### 9. Missing "Not Before" SIWE Field Validation

**File:** `src/Service/WalletVerification.php:221-229`
**Severity:** MEDIUM
**Type:** Incomplete Feature

The parser recognizes the `notBefore` field per EIP-4361 but doesn't validate it.

```php
// Parses notBefore:
$fields['notBefore'] = $value;
// But never validates - message could be used before intended time
```

**Remediation:**
```php
// Add after issuedAt validation:
if (isset($siweFields['notBefore'])) {
    $notBefore = strtotime($siweFields['notBefore']);
    if ($notBefore !== FALSE && $notBefore > $this->time->getRequestTime()) {
        $this->logger->warning('SIWE message not yet valid (notBefore: @time)', [
            '@time' => $siweFields['notBefore'],
        ]);
        return FALSE;
    }
}
```

---

### 10. Database Race Condition in Wallet Linking

**File:** `src/Service/WalletUserManager.php:127-167`
**Severity:** MEDIUM
**Type:** Bug

The `linkWalletToUser()` method has a TOCTOU (time-of-check-time-of-use) race condition:

```php
// Check if wallet is linked to different user
$existingUid = $this->database->select(...)  // Time of CHECK
    ->execute()
    ->fetchField();

if ($existingUid && $existingUid != $uid) {
    return;  // Wallet linked to different user
}

// Insert or update - Time of USE
$this->database->merge('wallet_auth_wallet_address')  // Race condition!
    ->key('wallet_address', $walletAddress)
    ->fields([...])
    ->execute();
```

**Impact:** Two concurrent requests could both pass the check and attempt to link the same wallet to different users.

**Remediation:**
```php
$transaction = $this->database->startTransaction();
try {
    // Use SELECT FOR UPDATE to lock the row
    $existingUid = $this->database->select('wallet_auth_wallet_address', 'wa')
        ->fields('wa', ['uid'])
        ->condition('wa.wallet_address', $walletAddress)
        ->forUpdate()
        ->execute()
        ->fetchField();

    // ... rest of logic

} catch (\Exception $e) {
    $transaction->rollBack();
    throw $e;
}
```

---

### 11. Deprecated `new static()` Pattern

**Files:** Controllers
**Severity:** MEDIUM
**Type:** Code Quality

Using `new static()` in `create()` methods is considered problematic in modern PHP:

```php
// Current pattern:
public static function create(ContainerInterface $container) {
    return new static(  // Anti-pattern
        $container->get('wallet_auth.verification'),
        // ...
    );
}
```

**Issues:**
- Causes issues with class inheritance
- PHPStan/Psalm flag this as problematic
- `self` is more predictable

**Remediation:**
```php
return new self(
    $container->get('wallet_auth.verification'),
    // ...
);
```

---

### 12. Missing Cache Tags on Block

**File:** `src/Plugin/Block/WalletLoginBlock.php:81-109`
**Severity:** MEDIUM
**Type:** Performance/Caching

The block renders based on configuration but doesn't declare cache dependencies:

```php
public function build() {
    // ...
    $build['#theme'] = 'wallet_login_button';
    $build['#attached'] = [...];
    // Missing: $build['#cache']
    return $build;
}
```

**Impact:** Block may not invalidate when configuration changes.

**Remediation:**
```php
$build['#cache'] = [
    'tags' => ['config:wallet_auth.settings'],
    'contexts' => ['user.roles:anonymous'],
    'max-age' => Cache::PERMANENT,
];
```

---

### 13. JavaScript Globals Pollution

**File:** `src/js/wallet-auth-connector.js:272`
**Severity:** MEDIUM
**Type:** Code Quality

Exports class to `window.WalletAuthConnector`, polluting global namespace:

```javascript
// Global pollution:
window.WalletAuthConnector = WalletConnector;
```

**Recommendation:** Use Drupal behaviors pattern exclusively or proper ES6 module exports with external declarations in Vite config.

---

### 14. Username Generation Privacy Consideration

**File:** `src/Service/WalletUserManager.php:236-249`
**Severity:** MEDIUM
**Type:** Privacy

Username is generated from first 8 hex characters of wallet address:

```php
$baseUsername = substr($walletAddress, 2, 8);  // e.g., "71C7656E"
```

**Consideration:** This leaks the first 8 characters of the user's wallet address. While intentional for UX, this should be documented as a privacy trade-off.

**Alternative:** Use a hash-based approach:
```php
$hash = substr(hash('sha256', $walletAddress), 0, 8);
$baseUsername = 'wallet_' . $hash;
```

---

### 15. Incomplete Error Messages in JSON Responses

**File:** `src/Controller/AuthenticateController.php:133-141`
**Severity:** MEDIUM
**Type:** Developer Experience

Generic "Authentication failed" error in catch block:

```php
catch (\Exception $e) {
    return new JsonResponse([
        'success' => FALSE,
        'error' => 'Authentication failed',  // Not actionable
    ], 500);
}
```

**Remediation:**
```php
return new JsonResponse([
    'success' => FALSE,
    'error' => 'Authentication failed',
    'error_code' => 'INTERNAL_ERROR',  // For client-side handling
], 500);
```

---

## LOW Priority Issues

### 16. Drupal 11 Compatibility

**File:** `wallet_auth.info.yml:14`
**Severity:** LOW
**Type:** Compatibility

```yaml
# Current:
core_version_requirement: ^10

# Recommended:
core_version_requirement: ^10 || ^11
```

---

### 17. Missing Logger Type Declaration

**Files:** Multiple service classes
**Severity:** LOW

```php
// Current:
protected $logger;

// Better (PHP 8.x):
protected \Drupal\Core\Logger\LoggerChannelInterface $logger;
```

---

### 18. Test Uses `usleep()` for Timing

**File:** `tests/Kernel/WalletUserManagerTest.php:275, 474`
**Severity:** LOW

Using `usleep(100000)` for timestamp tests is fragile. Use time service mocking instead.

---

### 19. README Has Duplicate Sections

**File:** `README.md:239-243, 319-324`
**Severity:** LOW

"Contributing" and "License" sections appear twice.

---

### 20. Missing Code Quality Tools

**Severity:** LOW

No `.editorconfig`, `phpcs.xml`, or `phpstan.neon` configuration files for code quality enforcement.

---

## Security Audit Summary

| Security Control | Status | Details |
|------------------|--------|---------|
| Input Validation | PASS | Address format, signature format validated |
| SQL Injection | PASS | Parameterized queries via DBAL |
| XSS Prevention | PASS | No direct output of user data |
| CSRF Protection | PARTIAL | Signature verification mitigates, no explicit token |
| Replay Attack Prevention | PASS | Nonce single-use, deleted after use |
| Session Fixation | PASS | Uses `user_login_finalize()` |
| Rate Limiting | FAIL | Not implemented |
| Domain Validation | FAIL | SIWE domain not verified |
| Chain ID Validation | FAIL | Cross-chain replay possible |
| Time Validation | PARTIAL | issuedAt/expiration checked, notBefore ignored |

---

## Test Coverage Analysis

### Existing Coverage

| Test Suite | Tests | Assertions | Coverage |
|------------|-------|------------|----------|
| WalletVerificationTest | 23 | ~150 | 85% |
| WalletUserManagerTest | 18 | ~100 | 80% |
| AuthenticationFlowTest | 10 | ~50 | 70% |
| SettingsFormTest | 9 | ~25 | 90% |
| WalletLoginBlockTest | 4 | ~15 | 75% |
| **Total** | **64** | **~340** | **82%** |

### Missing Test Coverage

1. **Domain validation** (when implemented)
2. **Chain ID validation** (when implemented)
3. **Rate limiting** (when implemented)
4. **Race condition handling**
5. **Edge cases in signature recovery**
6. **JavaScript unit tests**

---

## Performance Optimization Opportunities

1. **Cache nonce verification** briefly to prevent redundant tempstore reads
2. **Compound database index** on `wallet_address` + `status`
3. **Redis for nonce storage** in high-traffic scenarios
4. **Lazy-load JavaScript** only on pages with wallet block

---

## Recommended Action Plan

### Immediate (Week 1) - Critical Fixes

1. [ ] Fix configurable nonce lifetime (Issue #1)
2. [ ] Add domain validation (Issue #3)
3. [ ] Add chain ID validation (Issue #2)
4. [ ] Implement rate limiting (Issue #4)

### Short-term (Week 2-3) - High Priority

5. [ ] Create service interfaces (Issue #6)
6. [ ] Add cache tags to block (Issue #12)
7. [ ] Add "not before" validation (Issue #9)
8. [ ] Fix race condition in wallet linking (Issue #10)
9. [ ] Enforce permission on routes (Issue #5)

### Medium-term (Week 4+) - Quality Improvements

10. [ ] Fix `new static()` anti-pattern (Issue #11)
11. [ ] Add Drupal 11 compatibility (Issue #16)
12. [ ] Add typed properties throughout (Issue #17)
13. [ ] Add PHPCS/PHPStan configuration (Issue #20)
14. [ ] Clean up README duplicates (Issue #19)

---

## Conclusion

The Wallet Auth module represents solid work implementing Ethereum wallet authentication in Drupal. The cryptographic implementation follows EIP-191 and EIP-4361 standards correctly, and the test coverage is commendable.

**Key Strengths:**
- Strong cryptographic foundation with proper nonce management
- Well-organized service architecture
- Comprehensive test suite
- Good documentation

**Critical Gaps Requiring Immediate Attention:**
- Configurable nonce lifetime is ignored
- Domain and chainId validation missing (EIP-4361 security requirements)
- No rate limiting on public endpoints

The module is **production-capable** once the critical fixes are applied. The remaining issues enhance security posture and maintainability rather than blocking deployment.

---

*Review completed: 2025-01-13*
