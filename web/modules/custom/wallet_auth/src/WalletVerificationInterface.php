<?php

declare(strict_types=1);

namespace Drupal\wallet_auth;

/**
 * Interface for wallet signature verification and nonce management.
 */
interface WalletVerificationInterface {

  /**
   * Generate a cryptographically random nonce.
   *
   * @return string
   *   Base64-encoded random nonce.
   */
  public function generateNonce(): string;

  /**
   * Store a nonce in temporary storage.
   *
   * @param string $nonce
   *   The nonce to store.
   * @param string $walletAddress
   *   The wallet address requesting authentication.
   */
  public function storeNonce(string $nonce, string $walletAddress): void;

  /**
   * Verify a nonce exists and has not expired.
   *
   * @param string $nonce
   *   The nonce to verify.
   * @param string $walletAddress
   *   The wallet address that should match the nonce.
   *
   * @return bool
   *   TRUE if nonce is valid and not expired, FALSE otherwise.
   */
  public function verifyNonce(string $nonce, string $walletAddress): bool;

  /**
   * Verify a SIWE (Sign-In with Ethereum) message and signature.
   *
   * This implements EIP-4361 message parsing and validation,
   * along with EIP-191 (personal_sign) signature verification.
   *
   * @param string $message
   *   The SIWE message that was signed.
   * @param string $signature
   *   The hex signature (0x-prefixed).
   * @param string $walletAddress
   *   The expected wallet address (0x-prefixed, checksummed).
   *
   * @return bool
   *   TRUE if signature and message are valid, FALSE otherwise.
   */
  public function verifySignature(string $message, string $signature, string $walletAddress): bool;

  /**
   * Validate an Ethereum address format and checksum.
   *
   * @param string $address
   *   The address to validate.
   *
   * @return bool
   *   TRUE if address is valid, FALSE otherwise.
   */
  public function validateAddress(string $address): bool;

  /**
   * Delete a nonce from storage after use.
   *
   * @param string $nonce
   *   The nonce to delete.
   */
  public function deleteNonce(string $nonce): void;

}
