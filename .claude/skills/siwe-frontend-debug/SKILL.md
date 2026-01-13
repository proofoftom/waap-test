---
name: siwe-frontend-debug
description: Debug SIWE (Sign-In with Ethereum) frontend wallet authentication issues. Use when encountering signature verification failures, UI state problems, or message signing bugs in wallet authentication flows. Trigger phrases: "signature verification failed", "address mismatch", "wallet authentication bug", "SIWE not working", "message signing error", "UI stuck on signing", "user cancel not working".
---

# SIWE Frontend Debugging Guide

Debug wallet authentication frontend issues, particularly around SIWE message signing, signature verification failures, and UI state management.

## The #1 Bug: Message Regeneration Anti-Pattern

### Symptoms
- Signature verification fails with "address mismatch"
- Recovered address is different each login attempt
- Wallet SDK shows correct recovery in console, but backend returns wrong address
- Error logs show completely different recovered addresses

### Root Cause
The message is being signed with one set of timestamps, but a DIFFERENT message (with new timestamps) is sent to the backend for verification.

```javascript
// ❌ WRONG: Message regenerated with new timestamps
authenticate: function (address) {
  this.fetchNonce(address).then(function (data) {
    var message = self.createSignMessage(address, data.nonce); // T1
    return self.connector.signMessage(message);
  }).then(function (signature) {
    // BUG: Creates NEW message with timestamps T2!
    var message = self.createSignMessage(address, nonce);
    return self.sendAuthentication(address, signature, message);
  });
}
```

### The Fix
Pass the EXACT same signed message through the entire flow:

```javascript
// ✅ CORRECT: Pass the original signed message
authenticate: function (address) {
  this.fetchNonce(address).then(function (data) {
    var nonce = data.nonce;
    var message = self.createSignMessage(address, nonce); // Created once at T1

    return self.connector.signMessage(message).then(function (signature) {
      // Return ALL the data from this signing attempt
      return {
        signature: signature,
        message: message,    // The SAME message that was signed
        nonce: nonce
      };
    });
  }).then(function (authData) {
    // Use the exact message that was signed
    return self.sendAuthentication(
      address,
      authData.signature,
      authData.message,  // Original message, NOT regenerated
      authData.nonce
    );
  });
}

// Update sendAuthentication to accept message as parameter
sendAuthentication: function (address, signature, message, nonce) {
  // Use the passed message, don't call createSignMessage again!
  return fetch('/api/authenticate', {
    body: JSON.stringify({
      wallet_address: address,
      signature: signature,
      message: message,  // Use parameter directly
      nonce: nonce
    })
  });
}
```

### How to Detect This Bug
Add logging to compare message content:

```javascript
console.log('Signing message:', message);
// After signature, before send:
console.log('Sending message:', message);
// On backend, log received message length and first 100 chars
```

If timestamps differ between signing and sending, you've found the bug.

## Bug #2: UI State Not Reset on User Cancellation

### Symptoms
- Button stays stuck on "Signing..." after user cancels
- User cannot try again without refreshing the page
- Error shows in console but UI doesn't update

### Root Cause
`setState()` doesn't automatically call `updateUI()` - you must call both.

```javascript
// ❌ WRONG
.catch(function (error) {
  self.setState('connected');  // UI doesn't update!
  self.showError('Cancelled');
});

// ✅ CORRECT
.catch(function (error) {
  self.setState('connected');
  self.updateUI();  // MUST call this explicitly!
  self.showError('Cancelled');
});
```

### Bonus: Distinguish Cancellation from Errors

```javascript
.catch(function (error) {
  // Check if user rejected (vs actual error)
  if (error.message?.includes('User rejected') ||
      error.message?.includes('user rejected') ||
      error.code === 4001) {
    // User cancelled - reset to connected state
    self.setState('connected');
    self.updateUI();
    self.showError('Signature request was cancelled. Please try again.');
  } else {
    // Actual error - show error state
    self.setState('error');
    self.updateUI();
    self.showError(error.message || 'Authentication failed');
  }
});
```

## Bug #3: SIWE Parser Off-by-One Error

### Symptoms
- "Invalid SIWE message format" errors
- Required fields like URI, nonce appear missing
- Message parses but first field is null/undefined

### Root Cause
Starting field loop at `$statementEnd + 1` instead of `$statementEnd`.

```php
// ❌ WRONG - Skips first field line
for ($i = $statementEnd + 1; $i < count($lines); $i++) {

// ✅ CORRECT - Start from the actual field line
for ($i = $statementEnd; $i < count($lines); $i++) {
```

### SIWE Message Structure Reference
```
Line 0: <domain> wants you to sign in with your Ethereum account:
Line 1: <address>
Line 2: (blank)
Line 3: <statement> (optional)
Line 4: (blank)
Line 5: URI: <uri>          <- First field, must NOT be skipped!
Line 6: Version: <version>
Line 7: Chain ID: <chainId>
Line 8: Nonce: <nonce>
...
```

## Bug #4: EIP-155 Signature Normalization

### Symptoms
- `AssertionError: "assert((3 & $j) === $j)"` in elliptic-php
- `gmp_init(): Argument #1 ($num) is not an integer string`
- Signature verification fails even with correct message
- Wallet address is correct but signature doesn't verify

### Root Cause
EIP-155 signatures use `v = chainId * 2 + 35` (or 36), which can be much larger (53+) than the expected 27-30 range. Additionally, elliptic-php expects hex-encoded strings, not binary data, and expects the recovery ID (0-3), not the raw v value.

### The Fix

```php
// Extract r, s, v from signature (65 bytes)
$r = substr($signatureBin, 0, 32);
$s = substr($signatureBin, 32, 32);
$v = ord(substr($signatureBin, 64, 1));

// Normalize EIP-155 v to EIP-191 range (27-28)
if ($v >= 35) {
  $v = 27 + (($v - 35) % 2);
}
elseif ($v < 27) {
  $v += 27;
}

// Convert binary to hex for elliptic-php
$rHex = bin2hex($r);
$sHex = bin2hex($s);
$hashHex = bin2hex($hash);

// Pass recovery ID (0-3), not raw v (27-30)
$recoveryId = $v - 27;
$ec = new EC('secp256k1');
$pubKey = $ec->recoverPubKey($hashHex, ['r' => $rHex, 's' => $sHex], $recoveryId);
```

### Detection
Add debug logging before calling `recoverPubKey()`:

```php
$this->logger->debug('Signature values: v=@v, r_len=@r_len, s_len=@s_len', [
  '@v' => $v,
  '@r_len' => strlen($r),
  '@s_len' => strlen($s),
]);
```

If v >= 35, you have an EIP-155 signature that needs normalization.

## Bug #5: Third-Party SDK Session Persistence

### Symptoms
- Logging out of your app immediately logs you back in on page reload
- Auto-authentication happens without user interaction
- "Wallet ping timed out" or postMessage errors in console
- User can't stay logged out

### Root Cause
Wallet SDKs (WaaP, Web3Modal, WalletConnect, etc.) maintain their own sessions via localStorage/cookies, independent of your app's session. On page load, detecting an active SDK session triggers auto-login to your backend.

### The Fix

**1. Don't auto-authenticate on session detection:**
```javascript
// ❌ WRONG - auto-triggers backend authentication
this.connector.checkSession().then(function (account) {
  if (account) {
    self.authenticate(account);  // Immediate login!
  }
});

// ✅ CORRECT - update UI, wait for user action
this.connector.checkSession().then(function (account) {
  if (account) {
    self.setState('connected');
    self.updateUI();  // Just show connected state
  }
});
```

**2. Check for existing connection before re-login:**
```javascript
handleLogin: function () {
  // Skip SDK login if already connected
  var existingAddress = this.connector.getAddress();
  if (existingAddress) {
    this.authenticate(existingAddress);
    return;
  }
  // Proceed with full login flow...
}
```

**3. Show both "Sign in" and "Disconnect" when connected:**
```javascript
case 'connected':
  // User is connected to SDK but not authenticated in backend
  $loginButton.removeClass('visually-hidden').find('span').text('Sign in');
  $disconnectButton.removeClass('visually-hidden');
  $status.text('Connected: ' + this.formatAddress(this.connector.getAddress()));
  break;
```

### Detection
Check your init flow for `authenticate()` calls inside `checkSession()` promise chains.

## Bug #6: Vite Build - Missing Node Polyfills

### Symptoms
- `process is not defined` in browser console
- `Buffer is not defined` or `global is not defined`
- Wallet SDK fails to initialize
- Build succeeds but runtime errors occur

### Root Cause
Wallet SDKs (WaaP, viem, etc.) use Node.js globals not available in browser. Vite doesn't polyfill these by default (unlike Webpack).

### The Fix

Install and configure `vite-plugin-node-polyfills`:

```bash
npm install --save-dev vite-plugin-node-polyfills
```

Update `vite.config.js`:

```javascript
import { defineConfig } from 'vite';
import { nodePolyfills } from 'vite-plugin-node-polyfills';

export default defineConfig({
  plugins: [
    nodePolyfills({
      globals: {
        Buffer: true,
        global: true,
        process: true,
      },
      process: true,  // Polyfill process.env
    }),
  ],
  build: {
    // your build config
  },
});
```

### Detection
Look for console errors mentioning `process`, `Buffer`, or `global` being undefined on page load.

## Quick Debugging Checklist

When signature verification fails:

- [ ] **Message Consistency**: Is the exact same message being signed and verified? (Add logging)
- [ ] **Timestamp Regeneration**: Are `issuedAt`/`expirationTime` being recreated anywhere?
- [ ] **Parameter Passing**: Does `sendAuthentication()` receive the original message as a parameter?
- [ ] **SIWE Parsing**: Does the parser skip the first field line? Check for `+ 1` in loop.
- [ ] **UI Update**: Does error handler call both `setState()` AND `updateUI()`?
- [ ] **Cancellation**: Is error code 4001 handled differently from actual errors?
- [ ] **v Value Range**: Is v >= 35? Check for EIP-155 signature needing normalization
- [ ] **Binary vs Hex**: Is `bin2hex()` called before passing to elliptic-php?
- [ ] **Recovery ID**: Are you passing `v - 27` (0-3) or raw `v` (27-30)?
- [ ] **SDK Session**: Does page load trigger auto-auth via `checkSession()`?
- [ ] **Node Globals**: Does console show `process is not defined`? Need polyfills.

## Common Error Patterns

| Console/Backend Log | Likely Cause |
|---------------------|--------------|
| `address mismatch` + different recovered address each time | Message regeneration bug |
| `Invalid SIWE message format` | Parser off-by-one or missing field |
| `assert((3 & $j) === $j)` in elliptic-php | EIP-155 v value not normalized (use `v = 27 + ((v - 35) % 2)`) |
| `gmp_init(): Argument #1 is not an integer string` | Binary data passed instead of hex (use `bin2hex()`) |
| `process is not defined` | Missing Node polyfills in Vite build |
| Button stuck on "Signing..." | `updateUI()` not called in error handler |
| Auto-login after logout | `checkSession()` calling `authenticate()` automatically |
| Frontend logs correct address, backend logs different address | Message content differs between sign and verify |

## Testing the Fix

After implementing the fix:

1. **Happy Path**: Complete sign-in should succeed
2. **Cancel Test**: Cancel signature → button returns to "Sign in"
3. **Multiple Attempts**: Sign in, cancel, sign in again - all should work
4. **Timestamp Verification**: Backend should validate `issuedAt` and `expirationTime`
5. **Logout Persistence**: Log out → stay logged out (no auto re-login on refresh)
6. **EIP-155 Signatures**: Test with wallets that use chain ID (v >= 35)
7. **Node Polyfills**: No "process is not defined" errors in console

## Related Files from Phase 4 Implementation

- `web/modules/custom/wallet_auth/src/js/wallet-auth-ui.js` - Frontend UI and authentication flow
- `web/modules/custom/wallet_auth/src/Service/WalletVerification.php` - Backend SIWE parsing and verification
- `web/modules/custom/wallet_auth/src/Controller/AuthenticateController.php` - Authentication endpoint
- `web/modules/custom/wallet_auth/vite.config.connector.js` - Vite config for connector (Node polyfills)
- `web/modules/custom/wallet_auth/vite.config.ui.js` - Vite config for UI
