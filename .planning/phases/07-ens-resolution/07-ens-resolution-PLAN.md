# Phase 7 Plan: Add ENS Resolution to wallet_auth

**Phase:** 7 — Add ENS Resolution to wallet_auth
**Milestone:** 2.0 (Merge siwe_login into wallet_auth)
**Created:** 2025-01-17
**Status:** Ready for Execution

---

## Objective

Port ENS (Ethereum Name Service) resolution capability into wallet_auth using HTTP-based JSON-RPC. This enables forward resolution (ENS name → address) and reverse resolution (address → ENS name) with caching, failover, and security verification.

---

## Execution Context

**Starting Point:** Spike code exists in `git stash@{0}` — apply and refine.

**Key Research Findings:**
1. HTTP-based JSON-RPC approach is correct (no web3p dependency)
2. Guzzle (Drupal's @http_client) handles all RPC calls
3. Forward verification after reverse lookup is critical for security
4. Universal Resolver at `0xeEeEEEeE14D718C2B47D9923Deab1335E144EeEe` is future-proof

**Files from Stash:**
- `src/Service/EnsResolverInterface.php` (45 lines)
- `src/Service/EnsResolver.php` (513 lines)
- `src/Service/RpcProviderManager.php` (97 lines)
- Config schema updates
- Services.yml updates

---

## Context

**Project Structure:**
- Module path: `web/modules/custom/wallet_auth/`
- Separate Git repo (two-repo architecture)
- Existing services: `wallet_auth.verification`, `wallet_auth.user_manager`
- Existing tests: 7 test files (84+ tests)

**Dependencies Already Available:**
- `kornrunner/keccak` — Keccak-256 hashing (for namehash)
- `@http_client` — Guzzle HTTP client (Drupal core)
- `@cache.default` — Drupal cache backend
- `@logger.factory` — Logging

**No New Composer Dependencies Required**

---

## Tasks

### Task 1: Apply Stashed Spike Code

**Action:** Apply the stashed code to the working directory.

```bash
cd /Users/proofoftom/Code/os-decoupled/fresh2
git stash pop stash@{0}
```

**Expected Files:**
- `web/modules/custom/wallet_auth/src/Service/EnsResolverInterface.php`
- `web/modules/custom/wallet_auth/src/Service/EnsResolver.php`
- `web/modules/custom/wallet_auth/src/Service/RpcProviderManager.php`
- Updates to `wallet_auth.services.yml`
- Updates to `config/schema/wallet_auth.schema.yml`
- Updates to `config/install/wallet_auth.settings.yml`

**Checkpoint:** Verify all 6 files are present in working directory.

---

### Task 2: Run PHPCS and Fix Coding Standards

**Action:** Check and fix Drupal coding standards violations.

```bash
cd /Users/proofoftom/Code/os-decoupled/fresh2
vendor/bin/phpcs web/modules/custom/wallet_auth/src/Service/EnsResolver*.php web/modules/custom/wallet_auth/src/Service/RpcProviderManager.php
vendor/bin/phpcbf web/modules/custom/wallet_auth/src/Service/EnsResolver*.php web/modules/custom/wallet_auth/src/Service/RpcProviderManager.php
```

**Expected Issues (from spike):**
- TRUE/FALSE/NULL casing
- Line length violations
- Missing file-level docblocks

**Checkpoint:** PHPCS reports 0 errors on new files.

---

### Task 3: Run PHPStan and Fix Type Errors

**Action:** Run static analysis on the new service classes.

```bash
cd /Users/proofoftom/Code/os-decoupled/fresh2
vendor/bin/phpstan analyze web/modules/custom/wallet_auth/src/Service/EnsResolver*.php web/modules/custom/wallet_auth/src/Service/RpcProviderManager.php
```

**Potential Issues:**
- Type hints on return values
- Nullable type annotations
- Array type specificity

**Checkpoint:** PHPStan reports 0 errors at level 6.

---

### Task 4: Create EnsResolver Kernel Tests

**Create:** `web/modules/custom/wallet_auth/tests/src/Kernel/EnsResolverTest.php`

**Test Cases:**

1. **Forward Resolution Tests:**
   - `testResolveNameWithValidName()` — Mock RPC response for vitalik.eth
   - `testResolveNameWithInvalidName()` — Returns NULL for non-existent name
   - `testResolveNameCaching()` — Second call uses cache
   - `testResolveNameWithZeroAddress()` — Returns NULL for zero address resolver

2. **Reverse Resolution Tests:**
   - `testResolveAddressWithPrimaryName()` — Address with ENS returns name
   - `testResolveAddressWithNoPrimaryName()` — Returns NULL
   - `testResolveAddressForwardVerification()` — Rejects spoofed reverse records
   - `testResolveAddressCaching()` — Caches results

3. **RPC Failover Tests:**
   - `testFailoverOnPrimaryFailure()` — Falls back to secondary provider
   - `testFailoverExhaustsAllProviders()` — Graceful NULL on all failures
   - `testLogsWarningOnFailover()` — Warning logged when switching providers

4. **Namehash Algorithm Tests:**
   - `testNamehashEmptyString()` — Returns zero hash
   - `testNamehashEthTLD()` — Correct hash for "eth"
   - `testNamehashVitalikEth()` — Known hash for "vitalik.eth"
   - `testNamehashSubdomain()` — Correct hash for "sub.vitalik.eth"

5. **Cache Invalidation Tests:**
   - `testClearCacheForName()` — Clears forward cache
   - `testClearCacheForAddress()` — Clears reverse cache

**Testing Approach:**
- Mock `@http_client` service to return controlled RPC responses
- Use Drupal's test cache backend
- Verify log messages via test logger

**Checkpoint:** All tests pass, covering key functionality.

---

### Task 5: Create RpcProviderManager Unit Tests

**Create:** `web/modules/custom/wallet_auth/tests/src/Kernel/RpcProviderManagerTest.php`

**Test Cases:**

1. `testDefaultEndpointsUsedWhenNoConfig()` — Returns 4 default endpoints
2. `testCustomPrimaryProviderFirst()` — Custom URL is first in list
3. `testFallbackUrlsIncluded()` — Config fallbacks come before defaults
4. `testInvalidUrlsFiltered()` — Invalid URLs not included
5. `testDuplicateUrlsFiltered()` — Same URL not repeated

**Checkpoint:** All tests pass.

---

### Task 6: Run Full Test Suite

**Action:** Ensure new code doesn't break existing tests.

```bash
cd /Users/proofoftom/Code/os-decoupled/fresh2/web
../vendor/bin/phpunit -c phpunit.xml modules/custom/wallet_auth/tests/
```

**Expected:** All 84+ existing tests + new ENS tests pass.

**Checkpoint:** Full test suite green.

---

### Task 7: Commit Changes

**Action:** Commit in both repos (module + project).

```bash
# Commit in module repo
cd /Users/proofoftom/Code/os-decoupled/fresh2/web/modules/custom/wallet_auth
git add .
git commit -m "feat: add ENS resolution service with HTTP-based JSON-RPC

- Add EnsResolverInterface with forward/reverse resolution methods
- Add EnsResolver with namehash algorithm, caching, and RPC failover
- Add RpcProviderManager for provider URL management
- Add ENS config options: enable_ens_resolution, ens_cache_ttl, provider URLs
- Add comprehensive Kernel tests for ENS resolution and failover behavior
- No new Composer dependencies (uses Guzzle + kornrunner/keccak)"

# Commit in project repo
cd /Users/proofoftom/Code/os-decoupled/fresh2
git add .
git commit -m "feat(wallet_auth): add ENS resolution service (Phase 7)"
```

---

## Verification

### Code Quality Checks
- [ ] PHPCS: 0 errors on new files
- [ ] PHPStan: 0 errors at level 6
- [ ] All new tests pass
- [ ] All existing tests still pass

### Functional Verification
The ENS resolution service is passive (not wired into auth flow yet). Verification:

```php
// In a custom Drupal route or drush command:
$resolver = \Drupal::service('wallet_auth.ens_resolver');

// Forward resolution
$address = $resolver->resolveName('vitalik.eth');
// Expected: 0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045

// Reverse resolution
$name = $resolver->resolveAddress('0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045');
// Expected: vitalik.eth
```

---

## Success Criteria

1. **New Services Registered:**
   - `wallet_auth.rpc_provider_manager`
   - `wallet_auth.ens_resolver`

2. **New Files Created:**
   - `src/Service/EnsResolverInterface.php`
   - `src/Service/EnsResolver.php`
   - `src/Service/RpcProviderManager.php`
   - `tests/src/Kernel/EnsResolverTest.php`
   - `tests/src/Kernel/RpcProviderManagerTest.php`

3. **Config Schema Updated:**
   - `enable_ens_resolution` (boolean)
   - `enable_reverse_ens_lookup` (boolean)
   - `ens_cache_ttl` (integer)
   - `ethereum_provider_url` (string)
   - `ethereum_fallback_urls` (sequence)

4. **Quality Metrics:**
   - PHPCS: 0 errors
   - PHPStan: 0 errors
   - Test coverage: 90%+ on new services

---

## Output

**Phase 7 Complete When:**
1. All tasks marked complete
2. All verification checks pass
3. Commits pushed to both repos
4. STATE.md updated to reflect Phase 7 complete, Phase 8 pending

**Next Phase:** Phase 8 — Extend WalletAddress Entity with `ens_name` field and user display preference.

---

## Notes

- The ENS services are not yet integrated into the auth flow. Phase 8+ will wire them into WalletAddress entity.
- Universal Resolver contract support can be added later for future-proofing, but current approach is compatible.
- Admin UI for RPC endpoints is optional enhancement (can use config/sync).
