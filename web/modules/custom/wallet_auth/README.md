# Wallet Auth Module

A Drupal 10 module that provides wallet-based authentication using cryptographic signature verification (EIP-191 personal_sign).

## Requirements

- Drupal 10.6+
- PHP 8.2+
- External Auth module (drupal/externalauth)

## Dependencies

The following PHP packages are required:

- `kornrunner/keccak` - Keccak-256 hashing
- `simplito/elliptic-php` - Elliptic curve cryptography (secp256k1)

## Installation

1. Copy the `wallet_auth` directory to your Drupal installation's
   `web/modules/custom/` directory.
2. Enable the module via Drush: `drush pm:enable wallet_auth -y`
   or through the admin interface at `/admin/modules`.
3. Clear caches: `drush cache:rebuild`

## Architecture

### Services

The module provides two main services:

#### `wallet_auth.verification`

Handles cryptographic verification of wallet signatures.

- `generateNonce(): string` - Generate a cryptographically random nonce
- `storeNonce(string $nonce, string $walletAddress): void` - Store nonce in temp storage
- `verifyNonce(string $nonce, string $walletAddress): bool` - Verify nonce validity
- `verifySignature(string $message, string $signature, string $walletAddress): bool` - Verify EIP-191 signature
- `validateAddress(string $walletAddress): bool` - Validate Ethereum address format and EIP-55 checksum
- `deleteNonce(string $nonce): void` - Delete nonce after use

#### `wallet_auth.user_manager`

Manages wallet-to-user mapping and user creation.

- `loadUserByWalletAddress(string $walletAddress): ?UserInterface` - Load user by wallet
- `linkWalletToUser(string $walletAddress, int $uid): void` - Link wallet to user
- `createUserFromWallet(string $walletAddress): UserInterface` - Create new user from wallet
- `loginOrCreateUser(string $walletAddress): UserInterface` - Login or create user
- `getUserWallets(int $uid): array` - Get all wallets for a user

### Database Schema

The module creates a `wallet_auth_wallet_address` table with:

- `id` - Primary key
- `wallet_address` - Ethereum address (unique, 42 chars)
- `uid` - Associated Drupal user ID
- `created` - Unix timestamp when wallet was first linked
- `last_used` - Unix timestamp of last authentication
- `status` - Active/inactive flag (1=active, 0=disabled)

## REST API

### POST /wallet-auth/authenticate

Authenticate a user using their wallet signature.

**Request:**

```json
{
  "wallet_address": "0x...",
  "signature": "0x...",
  "message": "Sign this message to authenticate...",
  "nonce": "base64encodednonce..."
}
```

**Response (Success):**

```json
{
  "success": true,
  "uid": 123,
  "username": "wallet_0x1234abcd"
}
```

**Response (Error):**

```json
{
  "success": false,
  "error": "Invalid signature"
}
```

**Status Codes:**

- `200` - Authentication successful
- `400` - Invalid request (bad address, expired nonce)
- `401` - Invalid signature
- `500` - Server error

## Authentication Flow

1. **Request Nonce**: Client requests a nonce from the backend
2. **Sign Message**: Client signs the message using `personal_sign` (EIP-191)
3. **Submit Credentials**: Client sends wallet address, signature, message, and nonce
4. **Verification**: Backend validates:
   - Wallet address format and checksum
   - Nonce exists and is not expired
   - Signature matches the address
5. **User Login**: Backend creates/logs in user and establishes session

## Testing

A test script is available at `test-wallet-auth.php` for manual testing:

```bash
ddev exec php test-wallet-auth.php
```

## Development Roadmap

This module is being developed as part of a phased approach:

- **Phase 1**: Foundation & Environment Setup ✅
- **Phase 2**: Wallet as a Protocol Integration Research ✅
- **Phase 3**: Backend Authentication System ✅
- **Phase 4**: Frontend Wallet Integration (Next)
- **Phase 5**: Testing & Quality Assurance
- **Phase 6**: Documentation & Deployment

## Security Considerations

- Nonces are cryptographically random, single-use, and expire after 5 minutes
- Private keys are NEVER stored - only wallet addresses
- Signature verification uses established crypto libraries (no hand-rolled crypto)
- All inputs are validated and sanitized
- Database queries use Drupal's prepared statement API

## Contributing

Contributions are welcome! Please see the project roadmap for planned features.

## License

This module is licensed under GPL-2.0-or-later.

## Credits

Developed as part of the Wallet as a Protocol Drupal integration project.
