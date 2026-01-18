# Phase 7 Research: ENS Resolution for wallet_auth

**Date:** 2025-01-16
**Status:** Complete
**Phase:** Add ENS Resolution to wallet_auth

---

## Executive Summary

This research investigates ENS (Ethereum Name Service) resolution approaches for the wallet_auth Drupal module. Key findings:

1. **Frontend-first with backend verification** is the recommended pattern
2. **HTTP-based JSON-RPC** (no web3p dependency) is the modern, maintainable approach
3. **Universal Resolver** (`0xeEeEEEeE14D718C2B47D9923Deab1335E144EeEe`) is the canonical entry point
4. **The spike code** already implements the correct architecture
5. **L2 support** coming via ENSv2 - current mainnet-only approach will remain compatible

---

## 1. ENS Resolution: Do We Need Backend Resolution?

### Key Question from Spike Notes

> Do we need backend ENS resolution at all? The frontend (WaaP SDK / SIWE message) can provide ENS name.

### Research Finding: **Yes, but optional**

Per the SIWE documentation:

> "ENS data resolved from their Ethereum address may be used as part of the authenticated session, such as checking that the address's default ENS name is `alisha.eth` before granting access."

**Recommended approach:** Frontend-first with optional backend verification.

| Approach | Pros | Cons |
|----------|------|------|
| **Trust Frontend Only** | Zero latency, no RPC calls | User could spoof ENS in SIWE message |
| **Backend Verification** | Trustless verification | Adds 100-500ms latency, RPC costs |
| **Hybrid (Recommended)** | Best of both - fast + secure | Slight complexity |

### Recommended Implementation

1. **First login:** Accept frontend-provided ENS from SIWE message, store it
2. **Background refresh:** Periodically verify/update via backend (cron job)
3. **On-demand verification:** Optional config to verify on each auth

This matches siwe_login's current approach: extract ENS from SIWE message first, fall back to reverse lookup.

---

## 2. ENS Resolution Architecture

### The ENS Protocol Stack

```
┌─────────────────────────────────────────────────────────────┐
│                    Universal Resolver                        │
│              0xeEeEEEeE14D718C2B47D9923Deab1335E144EeEe     │
│    (Recommended entry point - handles all resolution)       │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                       ENS Registry                           │
│              0x00000000000C2E074eC69A0dFb2997BA6C7d2e1e     │
│         (Returns resolver address for a given node)          │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                    Per-Name Resolver                         │
│            (Varies per name - Public Resolver or custom)     │
│     Methods: addr(), name(), text(), contenthash()          │
└─────────────────────────────────────────────────────────────┘
```

### Resolution Methods

**Forward Resolution (name → address):**
```
1. namehash("nick.eth") → bytes32 node
2. ENS_REGISTRY.resolver(node) → resolver_address
3. resolver.addr(node) → address
```

**Reverse Resolution (address → name):**
```
1. namehash("{address_lowercase}.addr.reverse") → bytes32 node
2. ENS_REGISTRY.resolver(node) → resolver_address
3. resolver.name(node) → ENS name
4. VERIFY: Forward resolve the name, confirm it matches address
```

**Critical Security Requirement:** Always verify reverse resolution with forward resolution to prevent spoofing.

---

## 3. PHP Library Options

### Option A: web3p/web3.php (Current siwe_login approach)

```php
use Web3\Web3;
use Web3\Contract;

$web3 = new Web3($rpc_url);
$contract = new Contract($web3->provider, $abi);
```

**Status:** Maintained but not actively developed (158 open issues)
**Concerns:**
- Callback-based API (not Promise-based)
- No official ENS support
- PHP 8.x compatibility concerns
- Complex dependency chain

### Option B: ophelios-studio/php-ethereum-ens (New library)

```php
use Ophelios\Ens\EnsService;

$ens = new EnsService($rpc_url);
$name = $ens->resolveEnsName($address); // reverse
$address = $ens->resolveAddress($name); // forward
```

**Status:** Active (v0.1.0 released August 2025)
**Pros:**
- Purpose-built for ENS
- Modern PHP
- Read-only (no private keys)
**Cons:**
- Depends on web3p/web3.php internally
- New library, limited production testing

### Option C: Raw HTTP JSON-RPC (Spike approach) ✅ RECOMMENDED

```php
// Using Guzzle (already in Drupal core)
$response = $http_client->post($rpc_url, [
    'json' => [
        'jsonrpc' => '2.0',
        'method' => 'eth_call',
        'params' => [$call_data, 'latest'],
        'id' => 1,
    ],
]);
```

**Pros:**
- Zero new dependencies
- Guzzle already in Drupal core
- Full control over implementation
- Uses kornrunner/keccak (already installed)
**Cons:**
- Manual ABI encoding/decoding
- More code to maintain

### Recommendation: Option C (Raw HTTP)

The spike implementation already follows this approach. Benefits:
1. No additional Composer dependencies
2. Simpler, more maintainable
3. Full control over caching, error handling
4. Already proven in spike code

---

## 4. Contract Addresses & Function Selectors

### Key Addresses (Same on Mainnet & Sepolia)

```php
// ENS Registry - the root of all ENS lookups
const ENS_REGISTRY = '0x00000000000C2E074eC69A0dFb2997BA6C7d2e1e';

// Universal Resolver - recommended modern entry point
const UNIVERSAL_RESOLVER = '0xeEeEEEeE14D718C2B47D9923Deab1335E144EeEe';

// Reverse Registrar Node Hash (precomputed)
const ADDR_REVERSE_NODE = '0x91d1777781884d03a6757a803996e38de2a42967fb37eeaca72729271025a9e2';
```

### Function Selectors (First 4 bytes of keccak256)

```php
// ENS Registry
const RESOLVER_SELECTOR = '0x0178b8bf';  // resolver(bytes32)

// Resolver methods
const ADDR_SELECTOR = '0x3b3b57de';      // addr(bytes32)
const NAME_SELECTOR = '0x691f3431';      // name(bytes32)
const TEXT_SELECTOR = '0x59d1d43c';      // text(bytes32,string)
```

---

## 5. Namehash Algorithm

The namehash algorithm is recursive and already implemented in the spike:

```php
function namehash(string $name): string {
    if (empty($name)) {
        return '0x' . str_repeat('0', 64);
    }

    $node = hex2bin(str_repeat('0', 64));
    $labels = array_reverse(explode('.', strtolower($name)));

    foreach ($labels as $label) {
        $labelHash = hex2bin(Keccak::hash($label, 256));
        $node = hex2bin(Keccak::hash($node . $labelHash, 256));
    }

    return '0x' . bin2hex($node);
}
```

**Note:** Name normalization (UTS-46) should be applied before namehash for non-ASCII characters. For `.eth` names, lowercase is sufficient.

---

## 6. L2 & Multi-Chain Considerations

### Current State (2025)

ENS is **primarily Mainnet-only**:
- ENS Registry and most names live on Ethereum L1
- Resolution always starts from Mainnet
- L2 addresses can be stored in ENS records (ENSIP-19 coinType)

### ENSv2 (Coming)

> "ENSv2 is our ambitious plan to extend ENS to Layer 2 while reimagining the protocol from the ground up." — ENS Labs, Sept 2024

Key points:
- Universal Resolver at `0xeEeEEEeE14D718C2B47D9923Deab1335E144EeEe` will be upgraded
- Using Universal Resolver now ensures forward compatibility
- L2 Primary Names are being developed

### Recommendation

1. **Keep Mainnet RPC endpoints** for ENS resolution (standard)
2. **Use Universal Resolver** contract when possible
3. **Current implementation will remain compatible** with ENSv2

---

## 7. Caching Strategy

### What to Cache

| Data | TTL | Rationale |
|------|-----|-----------|
| Forward resolution (name → addr) | 1 hour | Rarely changes |
| Reverse resolution (addr → name) | 1 hour | Primary name changes |
| "No ENS found" result | 15 min | User might set up ENS |
| RPC failures | 5 min | Transient errors |

### Cache Keys

```php
'wallet_auth:ens:forward:{normalized_name}'
'wallet_auth:ens:reverse:{lowercase_address}'
```

### Cache Invalidation

- Manual: Admin clears all ENS cache
- Per-user: Clear when user links new wallet
- Automatic: TTL expiration

---

## 8. Error Handling & Failover

### RPC Failover Strategy

The spike implements this correctly:

```php
$providers = [
    'https://eth.llamarpc.com',      // LlamaRPC (free)
    'https://ethereum-rpc.publicnode.com', // PublicNode (free)
    'https://rpc.ankr.com/eth',      // Ankr (free tier)
    'https://cloudflare-eth.com',    // Cloudflare
];
```

**Failover logic:**
1. Try primary provider
2. On failure (timeout/5xx), try next
3. Log warnings on failover
4. Return NULL after all fail (graceful degradation)

### Error Scenarios

| Error | Action |
|-------|--------|
| RPC timeout | Failover to next provider |
| RPC rate limit (429) | Failover + exponential backoff |
| No resolver found | Return NULL (valid - no ENS) |
| Forward verification fails | Return NULL + log warning (security) |
| Invalid address format | Throw exception |

---

## 9. What NOT to Hand-Roll

### Use Existing Solutions For

1. **Keccak-256 hashing:** `kornrunner/keccak` (already installed)
2. **HTTP client:** Drupal's `@http_client` service (Guzzle)
3. **Caching:** Drupal's `@cache.default` service
4. **Logging:** Drupal's `@logger.factory` service

### Do Hand-Roll

1. **Namehash algorithm** — Simple, well-documented, spike has it
2. **ABI encoding** — Only need basic uint256/bytes32/address
3. **RPC failover** — Drupal-specific integration needed

---

## 10. Recommended Changes to Spike Code

The spike code is solid. Minor improvements:

1. **Add Universal Resolver support** (optional, for future-proofing)
2. **Improve name normalization** for edge cases
3. **Add batch resolution** for multiple addresses
4. **Config UI** for provider URLs in admin settings

---

## 11. Open Questions Resolved

### Q: When should ENS resolution happen?

**A:** On first wallet link + periodic background refresh. Extract from SIWE message first (zero latency), verify/update via backend asynchronously.

### Q: How should ENS names be stored?

**A:** On WalletAddress entity (Phase 8 adds `ens_name` field). Also cached for fast lookups.

### Q: What about ENS names that change?

**A:** Background cron job refreshes ENS every 24h. Cache TTL of 1h for online lookups. User can manually trigger refresh.

### Q: Error handling for RPC failures?

**A:** Graceful degradation — auth succeeds without ENS data. Log error, retry on next auth or cron run.

### Q: Network considerations?

**A:** Mainnet only for now. Config allows custom RPC endpoints. ENSv2 compatibility via Universal Resolver.

---

## 12. Implementation Checklist

Based on this research, Phase 7 should:

- [ ] Port spike code with minor improvements
- [ ] Add EnsResolverInterface.php
- [ ] Add EnsResolver.php (HTTP-based, no web3p)
- [ ] Add RpcProviderManager.php
- [ ] Update services.yml
- [ ] Update config schema with ENS settings
- [ ] Update default config
- [ ] Add admin UI for RPC endpoints (optional)
- [ ] Write unit tests with mock HTTP client
- [ ] Test failover behavior

---

## Sources

1. ENS Documentation: https://docs.ens.domains/
2. Universal Resolver: https://docs.ens.domains/resolvers/universal/
3. SIWE ENS Profile Resolution: https://docs.login.xyz/additional-support/ens-profile-resolution
4. ENSv2 Update: https://ens.domains/blog/post/ensv2-update
5. ophelios-studio/php-ethereum-ens: https://github.com/ophelios-studio/php-ethereum-ens
6. Existing siwe_login implementation (analyzed in this codebase)
