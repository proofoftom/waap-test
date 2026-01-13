# Research: Wallet as a Protocol Integration

**Phase:** 2 - Wallet as a Protocol Integration Research
**Date:** 2025-01-12
**Mode:** yolo | **Depth:** standard

---

## Executive Summary

Wallet as a Protocol (WaaP) by human.tech is a **protocol-based, modular, decentralized** wallet infrastructure that provides **protected self-custody** through a 2PC-MPC (Two-Party Computation Multi-Party Computation) cryptographic model. Unlike Wallet-as-a-Service (WaaS) solutions, WaaP offers **true wallet ownership** without vendor lock-in, at **zero cost** to developers.

**Key Finding:** WaaP exposes an **EIP-1193-compliant interface** via `window.waap`, making it compatible with existing Web3 tooling (wagmi, ethers.js, viem) and enabling drop-in replacement for traditional wallet providers like MetaMask.

---

## Table of Contents

1. [WaaP Architecture & Security Model](#waap-architecture--security-model)
2. [SDK & Integration Approach](#sdk--integration-approach)
3. [Authentication Flow](#authentication-flow)
4. [Message Signing & Verification](#message-signing--verification)
5. [Drupal Integration Patterns](#drupal-integration-patterns)
6. [Standard Stack](#standard-stack)
7. [What NOT to Hand-Roll](#what-not-to-hand-roll)
8. [Common Pitfalls](#common-pitfalls)
9. [Architecture Decision Records](#architecture-decision-records)

---

## WaaP Architecture & Security Model

### What is WaaP?

**WaaP (Wallet as a Protocol)** is a decentralized wallet infrastructure that:

- **Splits keys into two shares**: One local, one distributed across Ika's decentralized MPC network
- **Provides protected self-custody**: Users own their wallets, apps cannot revoke access
- **Eliminates vendor lock-in**: Unlike WaaS (Privy, Magic, Arcana), a single WaaP account works across any dApp
- **Zero infrastructure cost**: Free to integrate (no subscription fees like WaaS)

### Comparison Table

| Feature | WaaP | WaaS (Privy, etc.) | Self-Custodial (MetaMask) |
|---------|------|-------------------|---------------------------|
| Architecture | Protocol-based, decentralized | Service-based, centralized | Client-based, decentralized |
| Cost | **Free** | Subscription-based | Free |
| Custody Model | Protected Self-Custody (2PC dual-share) | Semi-Custodial (key sharding) | Full Self-Custody (user risk) |
| Cross-App Usage | **Single account everywhere** | Siloed per app | Manual seed phrase |
| Key Recovery | **MPC network recovery** | Provider-dependent | User-managed phrases |

### Security Model

**2PC-MPC Cryptography:**
- User's key split into **two parts**:
  1. **Local share**: Stored on user's device
  2. **Network share**: Distributed across Ika's zero-trust MPC network
- **No single point of failure**: Compromising one share is insufficient
- **Zero-trust**: Network nodes don't have complete keys

**Sources:**
- [What is WaaP? | Wallet as a Protocol](https://docs.wallet.human.tech/)
- [Unveiling Wallet as a Protocol - Human Tech](https://human.tech/blog/unveiling-wallet-as-a-protocol)
- [Understanding Wallet-as-a-Protocol](https://www.onesafe.io/blog/wallet-as-a-protocol-decentralized-infrastructure)

---

## SDK & Integration Approach

### Official SDK

**Package:** `@human.tech/waap-sdk`
**Installation:** `npm install @human.tech/waap-sdk`
**Context7 Library:** `/websites/wallet_human_tech` (247 code snippets, High reputation)

### Core Methods

WaaP exposes an **EIP-1193-compliant provider** via `window.waap`:

```javascript
import { initWaaP } from "@human.tech/waap-sdk"

// Initialize WaaP (must be called first)
const initConfig = {
  config: {
    authenticationMethods: ['email', 'phone', 'social', 'wallet'],
    allowedSocials: ['google', 'twitter', 'discord'],
    styles: { darkMode: true },
    showSecured: true
  },
  useStaging: false,
  walletConnectProjectId: "<PROJECT_ID>" // Required if 'wallet' enabled
}

initWaaP(initConfig)

// Use window.waap (EIP-1193 compatible)
const loginType = await window.waap.login()
const accounts = await window.waap.request({ method: 'eth_requestAccounts' })
const address = accounts[0]

// Sign message
const signature = await window.waap.request({
  method: "personal_sign",
  params: [message, address],
})

// Logout
await window.waap.logout()
```

### Integration Patterns

**Three integration approaches:**

1. **Plain (Direct)**: Use `window.waap` directly
2. **Wagmi v2**: Custom WaaP connector with wagmi
3. **Ethers v6**: WaaP as BrowserProvider

**Example repositories available:**
- `holonym-foundation/waap-examples/tree/main/waap-plain-nextjs`
- `holonym-foundation/waap-examples/tree/main/waap-wagmi-nextjs`
- `holonym-foundation/waap-examples/tree/main/waap-ethers-nextjs`

### Auto-Connect

WaaP automatically handles session persistence:
```javascript
// This will auto-connect if user was previously logged in
const accounts = await window.waap.request({ method: 'eth_requestAccounts' })
```

### Event Listening

```javascript
window.waap.on("accountsChanged", (accounts) => {
  console.log("Active account changed:", accounts[0]);
});
```

**Sources:**
- [Quick Start | Wallet as a Protocol](https://docs.wallet.human.tech/quick-start)
- [Methods Reference | Wallet as a Protocol](https://docs.wallet.human.tech/guides/methods)

---

## Authentication Flow

### High-Level Flow

```
┌─────────────┐         ┌─────────────┐         ┌─────────────┐
│   User      │         │   Drupal    │         │    WaaP     │
└──────┬──────┘         └──────┬──────┘         └──────┬──────┘
       │                       │                       │
       │  1. Click Login       │                       │
       │ ─────────────────────>│                       │
       │                       │                       │
       │                       │  2. initWaaP()        │
       │                       │ ─────────────────────>│
       │                       │                       │
       │                       │  3. window.waap.login()│
       │                       │ ─────────────────────>│
       │                       │                       │
       │  4. Select auth method│                       │
       │ <─────────────────────────────────────────────│
       │     (email/social/    │                       │
       │      wallet/WalletConnect)                    │
       │                       │                       │
       │  5. Complete auth     │                       │
       │ ─────────────────────────────────────────────>│
       │                       │                       │
       │                       │  6. Return accounts   │
       │                       │ <─────────────────────│
       │                       │                       │
       │  7. Generate nonce    │                       │
       │ <─────────────────────│                       │
       │                       │                       │
       │  8. Sign message      │                       │
       │ ─────────────────────────────────────────────>│
       │                       │                       │
       │  9. Return signature  │                       │
       │ <─────────────────────────────────────────────│
       │                       │                       │
       │ 10. Verify signature  │                       │
       │ ─────────────────────>│                       │
       │                       │                       │
       │ 11. Login/create user │                       │
       │ <─────────────────────│                       │
       │                       │                       │
```

### WaaP Login Types

When `window.waap.login()` is called, it returns:
- `'waap'`: User chose WaaP (email/phone/social)
- `'injected'`: User chose injected wallet (MetaMask, etc.)
- `'walletconnect'`: User chose WalletConnect
- `null`: User cancelled

**Important:** All login types result in an EIP-1193 provider, so subsequent code works identically.

---

## Message Signing & Verification

### Signing Approach

**WaaP uses EIP-191 compliant `personal_sign`:**

```javascript
const message = 'Sign this message to prove ownership of your wallet.';
const signature = await window.waap.request({
  method: "personal_sign",
  params: [message, address],
});
```

### Backend Verification

**For Drupal backend verification, we have two approaches:**

#### Option A: Use SIWE (Sign-In with Ethereum) Standard

**Recommended** for standards compliance and interoperability.

**SIWE (EIP-4361)** provides:
- Standardized message format
- Built-in nonce handling
- Domain binding
- Time-based expiration
- Libraries for multiple languages

**SIWE Libraries:**
- TypeScript/JavaScript: `siwe` npm package (3.0.0, 231 dependents)
- PHP: No native SIWE library, but can implement verification manually

**SIWE Message Format:**
```
example.com wants you to sign in with your Ethereum account:
0xYourAddress

I hereby verify that I am the owner of this account

URI: https://example.com
Version: 1
Chain ID: 1
Nonce: [random-nonce]
Issued At: 2025-01-12T10:00:00.000Z
Expiration Time: 2025-01-12T10:05:00.000Z
```

**Sources:**
- [Sign-In with Ethereum (SIWE)](https://oxlib.sh/guides/siwe)
- [EIP-4361 | Sign-In with Ethereum](https://docs.login.xyz/general-information/siwe-overview/eip-4361)
- [spruceid/siwe GitHub](https://github.com/spruceid/siwe)

#### Option B: Manual Signature Verification

**Simpler approach** for basic wallet ownership verification.

**Process:**
1. Generate random nonce (store in session)
2. User signs message: "Prove ownership of {address} with nonce: {nonce}"
3. Backend verifies signature using elliptic curve cryptography

**PHP Implementation Options:**
- Use `kornrunner/ethereum-address` package for address recovery
- Use `web3.php` or custom eth_sign verification
- Verify signature matches address using ecrecover

**Verification Logic:**
```php
// Pseudo-code for PHP verification
$message = "Prove ownership of $address with nonce: $nonce";
$signature = $_POST['signature'];

// Recover address from signature
$recoveredAddress = recoverAddressFromSignature($message, $signature);

if (strtolower($recoveredAddress) === strtolower($address)) {
  // Signature valid - authenticate user
}
```

**Sources:**
- [Prove Wallet Ownership - MeshJS](https://meshjs.dev/guides/prove-wallet-ownership)
- [How to Verify Ethereum Wallet Ownership](https://yuichiroaoki.medium.com/how-to-verify-the-ownership-of-your-ethereum-wallet-e2b35c366c18)
- [Backend-based signature verification - Ethereum Stack Exchange](https://ethereum.stackexchange.com/questions/158664/backend-based-signature-verification)

### Recommendation

**Use SIWE (Option A)** for:
- Standards compliance
- Better security (domain binding, expiration)
- Interoperability with other SIWE implementations

**Use Manual Verification (Option B)** for:
- Simpler implementation
- Basic ownership proof only
- No domain binding required

---

## Drupal Integration Patterns

### Existing Web3 Authentication Modules

**Reference modules for Drupal 10:**

1. **[Web3 Auth](https://www.drupal.org/project/web3_auth)**
   - Drupal 10 compatible
   - Connects and authenticates with Web3 wallets and MetaMask
   - User management can be disabled

2. **[Sign-In with Ethereum (SIWE)](https://www.drupal.org/project/siwe_login)**
   - Implements EIP-4361 standard
   - Supports MetaMask and other Web3 wallets
   - Published November 24, 2025

3. **[Safe{Wallet} Smart Accounts](https://www.drupal.org/project/safe_smart_accounts)**
   - Ethereum Smart Wallet functionality
   - Multi-signature Safe accounts
   - Published December 12, 2025

4. **[External Auth](https://www.drupal.org/project/externalauth)**
   - Generic service for external authentication
   - Can be used as base module
   - Handles user registration and login from external sources

### Drupal Authentication Architecture

**Key Components:**

1. **Authentication Provider Plugin** (`@ConsumerAuthentication`)
   - Implements authentication logic
   - Located in `src/Plugin/Consumer/`
   - Extends `ConsumerAuthenticationBase`

2. **External Auth Service**
   - `externalauth.externalauth` service
   - Methods: `loginRegister()`, `register()`, `login()`
   - Handles user creation and linking

3. **Route & Controller**
   - REST endpoint for signature verification
   - Returns session or token

4. **Database Schema**
   - Table mapping wallet_address to uid (user ID)
   - One-to-many relationship (user can have multiple wallets)

### Recommended Architecture

**For wallet_auth module:**

```
wallet_auth/
├── src/
│   ├── Plugin/
│   │   └── Consumer/
│   │       └── WalletAuthConsumer.php    # Authentication provider
│   ├── Service/
│   │   ├── WalletVerification.php        # Signature verification
│   │   └── WalletUserManager.php         # User creation/linking
│   ├── Controller/
│   │   └── VerificationController.php    # REST endpoint
│   └── Entity/
│       └── WalletAddress.php             # Entity definition
├── config/
│   ├── schema/
│   │   └── wallet_address.schema.yml
│   └── install/
│       └── wallet_auth.install.yml       # Database schema
├── js/
│   ├── waap-sdk.js                       # Bundled WaaP SDK
│   └── wallet-auth.js                    # Our integration code
└── wallet_auth.libraries.yml
```

**Sources:**
- [Web3 Authentication and Crypto Wallets Login for Drupal](https://plugins.miniorange.com/drupal-web3-authentication)
- [REST API Authentication using External Identity Provider](https://www.drupal.org/docs/contributed-modules/api-authentication/rest-api-authentication-using-external-identity-provider)

---

## Standard Stack

### Frontend JavaScript

**Build Tool:** Webpack 5
**Why:** Industry standard, excellent Drupal integration

**Dependencies:**
```json
{
  "@human.tech/waap-sdk": "^1.0.0",
  "webpack": "^5.0.0",
  "webpack-cli": "^5.0.0"
}
```

**Build Configuration:**
- Use `drupal-libraries-webpack-plugin` to auto-generate `.libraries.yml` entries
- Output to `js/dist/` directory
- Source maps for development

**Alternative:** Use the [Webpack bundler Drupal module](https://www.drupal.org/project/webpack)

### Backend PHP

**Dependencies (via Composer):**
```json
{
  "kornrunner/ethereum-address": "^2.0",
  "simonetti/ebo-crypto": "^1.0"  // For signature verification
}
```

**Or:** Use `web3.php` for comprehensive Web3 functionality

### Module Structure

**Minimum Required:**
- `wallet_auth.info.yml` - Module definition
- `wallet_auth.libraries.yml` - JS library definitions
- `wallet_auth.routing.yml` - Routes
- `wallet_auth.permissions.yml` - Permissions
- `src/Plugin/Consumer/WalletAuthConsumer.php` - Auth provider
- `src/Service/WalletVerification.php` - Verification service
- `src/Controller/VerificationController.php` - REST endpoint

### Drupal Integration Tools

**Recommended Modules:**
- **[External Auth](https://www.drupal.org/project/externalauth)** - For user registration/linking
- **[Webpack bundler](https://www.drupal.org/project/webpack)** - For JS bundling (optional)

**NPM Packages:**
- **[drupal-libraries-webpack-plugin](https://www.npmjs.com/package/drupal-libraries-webpack-plugin)** - Auto-generate library definitions

**Sources:**
- [React in Drupal with Webpack](https://dev.to/nickbahson/reactjs-in-drupal-with-webpack-5a7b)
- [Using Webpack to Unleash Your Drupal 8 Project](https://shinesolutions.com/2018/11/05/using-webpack-to-unleash-your-drupal-8-project-with-modern-javascript/)
- [Adding Webpack to a traditional Drupal theme](https://thinkshout.com/blog/2019/10/adding-webpack-to-a-traditional-drupal-theme/)

---

## What NOT to Hand-Roll

### ❌ Do NOT Build Yourself

1. **Cryptographic signature verification**
   - Use existing libraries (SIWE, kornrunner/ethereum-address)
   - Do NOT implement elliptic curve cryptography from scratch

2. **Wallet connection UI**
   - Use WaaP's built-in login modal
   - Do NOT build custom wallet connection UI

3. **Session management**
   - Use Drupal's core session system
   - Use External Auth module for user handling

4. **Nonce generation**
   - Use Drupal's ` Crypt::randomBytesBase64()`
   - Do NOT use `rand()` or `mt_rand()`

5. **Webpack configuration from scratch**
   - Use existing Drupal webpack patterns
   - Use drupal-libraries-webpack-plugin

### ✅ What You Should Implement

1. **Authentication provider plugin**
   - Drupal-specific, must be custom

2. **User account creation/linking**
   - Application-specific logic

3. **Database schema**
   - Wallet address to user mapping

4. **REST API endpoint**
   - Signature verification route

5. **Drupal forms/blocks**
   - Login button, settings UI

---

## Common Pitfalls

### 1. **Assuming WaaP Requires WalletConnect**

**Pitfall:** Thinking WaaP only works with external wallets.

**Reality:** WaaP supports email/phone/social login WITHOUT external wallets. WalletConnect is only needed if you want to support external wallets as an option.

**Fix:** WaaP works out-of-box with just email/phone/social. Only add WalletConnect project ID if you enable 'wallet' authentication method.

### 2. **Not Using Auto-Connect**

**Pitfall:** Always calling `window.waap.login()` on page load.

**Reality:** WaaP automatically handles sessions. Just call `eth_requestAccounts` to check existing session.

**Fix:**
```javascript
// Check for existing session first
const accounts = await window.waap.request({ method: 'eth_requestAccounts' })
if (accounts.length === 0) {
  // No session, show login button
}
```

### 3. **Ignoring Login Type**

**Pitfall:** Not checking which login method user chose.

**Reality:** `window.waap.login()` returns the login type ('waap', 'injected', 'walletconnect', null).

**Fix:** Check login type to handle different scenarios (e.g., show different UI for injected vs WaaP).

### 4. **Weak Nonce Generation**

**Pitfall:** Using predictable nonces or reusing nonces.

**Reality:** Nonces must be cryptographically random and single-use.

**Fix:** Use `Crypt::randomBytesBase64(32)` and store in session with timestamp.

### 5. **Storing Private Keys or Seed Phrases**

**Pitfall:** Asking users for private keys or storing them.

**Reality:** Never ask for or store private keys. Only use message signing for ownership proof.

**Fix:** Use `personal_sign` for authentication, never request private keys.

### 6. **Not Validating Address Checksums**

**Pitfall:** Accepting invalid Ethereum addresses.

**Reality:** Ethereum addresses have checksums (mixed case).

**Fix:** Validate addresses using checksum validation before processing.

### 7. **Forgetting to Clean Up Expired Nonces**

**Pitfall:** Nonces accumulate in database/session indefinitely.

**Reality:** Old nonces should be deleted to prevent replay attacks.

**Fix:** Implement cron job to delete nonces older than 5 minutes.

### 8. **Missing Error Handling for Wallet Disconnection**

**Pitfall:** Assuming wallet stays connected.

**Reality:** Users can disconnect wallet at any time.

**Fix:** Listen for `accountsChanged` events and handle disconnection gracefully.

### 9. **Not Using Drupal's External Auth Module**

**Pitfall:** Hand-rolling user creation and login logic.

**Reality:** External Auth module handles this robustly.

**Fix:** Use `externalauth.externalauth` service for user registration/login.

### 10. **Hardcoding Chain ID**

**Pitfall:** Assuming Ethereum mainnet only.

**Reality:** WaaP supports multiple chains.

**Fix:** Make chain ID configurable, validate against user's current chain.

---

## Architecture Decision Records

### ADR-001: Use WaaP Over WaaS

**Decision:** Use Wallet as a Protocol (WaaP) instead of Wallet-as-a-Service (Privy, Magic, Arcana).

**Rationale:**
- **Zero cost** vs subscription fees
- **True ownership** vs vendor lock-in
- **Cross-app compatibility** vs siloed per app
- **Protocol-based** vs service-based

**Consequences:**
- ✅ No recurring infrastructure costs
- ✅ Users own wallets across apps
- ✅ No API rate limits
- ⚠️ Less mature than some WaaS solutions (but rapidly evolving)

### ADR-002: Use EIP-1193 Provider Interface

**Decision:** Use WaaP's EIP-1193 interface (`window.waap`) instead of custom SDK methods.

**Rationale:**
- **Standard interface** - Works with wagmi, ethers.js, viem
- **Drop-in replacement** - Can swap wallet providers without changing app code
- **Future-proof** - EIP-1193 is widely adopted

**Consequences:**
- ✅ Interoperability with existing Web3 tooling
- ✅ Easier to find examples and support
- ✅ Can add support for other wallets later

### ADR-003: Use SIWE (EIP-4361) for Authentication

**Decision:** Use Sign-In with Ethereum standard for message signing and verification.

**Rationale:**
- **Industry standard** - Widely adopted across Web3
- **Security features** - Domain binding, expiration, nonce handling
- **Libraries available** - TypeScript, JavaScript, PHP (partial)

**Consequences:**
- ✅ Standards compliance
- ✅ Better security than custom signing
- ✅ Interoperable with other SIWE implementations
- ⚠️ More complex than simple message signing

### ADR-004: Use External Auth Module

**Decision:** Use Drupal's External Auth module for user creation/login.

**Rationale:**
- **Battle-tested** - Handles edge cases
- **Drupal standard** - Used by other auth modules
- **Maintained** - Regular updates and security fixes

**Consequences:**
- ✅ Less custom code to maintain
- ✅ Handles user linking correctly
- ⚠️ Additional module dependency

### ADR-005: Use Webpack for JavaScript Bundling

**Decision:** Use Webpack 5 with drupal-libraries-webpack-plugin.

**Rationale:**
- **Drupal integration** - Auto-generates .libraries.yml entries
- **Industry standard** - Wide adoption and support
- **NPM packages** - Easy to import @human.tech/waap-sdk

**Consequences:**
- ✅ Automated build process
- ✅ Modern JavaScript support
- ⚠️ Additional build step in deployment

---

## Additional Resources

### Official Documentation
- [WaaP Documentation](https://docs.wallet.human.tech/)
- [WaaP Quick Start](https://docs.wallet.human.tech/quick-start)
- [WaaP Methods Reference](https://docs.wallet.human.tech/guides/methods)
- [WaaP Examples Repository](https://github.com/holonym-foundation/waap-examples)

### Standards & Specifications
- [EIP-1193: Ethereum Provider JavaScript API](https://eips.ethereum.org/EIPS/eip-1193)
- [EIP-4361: Sign-In with Ethereum](https://eips.ethereum.org/EIPS/eip-4361)
- [EIP-191: Signed Data Standard](https://eips.ethereum.org/EIPS/eip-191)

### Community & Support
- [WaaP Developer Telegram](https://t.me/waapdevelopers) (mentioned in docs)
- [WaaP on GitHub](https://github.com/holonym-foundation)
- [WaaP on X/Twitter](https://x.com/WaaPxyz)

### Related Projects
- [Drupal Web3 Auth Module](https://www.drupal.org/project/web3_auth)
- [Drupal SIWE Login Module](https://www.drupal.org/project/siwe_login)
- [Drupal Safe Smart Accounts](https://www.drupal.org/project/safe_smart_accounts)

### PHP/Web3 Integration
- [web3.php](https://github.com/web3-php/web3.php) - Ethereum PHP library
- [kornrunner/ethereum-address](https://github.com/kornrunner/php-ethereum-address) - Address utilities

---

## Next Steps

After this research, the next phase is **Phase 3: Backend Authentication System**.

**Key Implementation Tasks:**
1. Create database schema for wallet_address to user mapping
2. Implement WalletVerification service (signature verification)
3. Create authentication provider plugin
4. Implement user account creation on first auth
5. Add route for signature verification endpoint
6. Add proper permission and security controls

**To proceed with planning Phase 3, use:** `/gsd:plan-phase 3`

---

**Research completed:** 2025-01-12
**Total sources consulted:** 30+
**Cross-verification:** All major findings verified against official documentation and multiple sources
