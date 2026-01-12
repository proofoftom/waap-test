<?php

declare(strict_types=1);

namespace Drupal\wallet_auth\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Elliptic\EC;
use kornrunner\Keccak;

/**
 * Service for wallet signature verification and nonce management.
 */
class WalletVerification {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The private tempstore factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * Nonce lifetime in seconds (5 minutes).
   *
   * @var int
   */
  protected const NONCE_LIFETIME = 300;

  /**
   * Constructs a WalletVerification service.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   * @param \Drupal\Core\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The private tempstore factory.
   */
  public function __construct(
    Connection $database,
    LoggerChannelFactoryInterface $logger_factory,
    TimeInterface $time,
    PrivateTempStoreFactory $temp_store_factory,
  ) {
    $this->database = $database;
    $this->logger = $logger_factory->get('wallet_auth');
    $this->time = $time;
    $this->tempStoreFactory = $temp_store_factory;
  }

  /**
   * Generate a cryptographically random nonce.
   *
   * @return string
   *   Base64-encoded random nonce.
   */
  public function generateNonce(): string {
    try {
      $randomBytes = random_bytes(32);
      $nonce = rtrim(strtr(base64_encode($randomBytes), '+/', '-_'), '=');
      $this->logger->info('Generated new nonce for wallet authentication');
      return $nonce;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to generate nonce: @message', ['@message' => $e->getMessage()]);
      throw $e;
    }
  }

  /**
   * Store a nonce in temporary storage.
   *
   * @param string $nonce
   *   The nonce to store.
   * @param string $walletAddress
   *   The wallet address requesting authentication.
   */
  public function storeNonce(string $nonce, string $walletAddress): void {
    try {
      $store = $this->tempStoreFactory->get('wallet_auth');
      $store->set($nonce, [
        'wallet_address' => $walletAddress,
        'created' => $this->time->getRequestTime(),
      ]);
      $this->logger->info('Stored nonce for wallet @wallet', ['@wallet' => $walletAddress]);
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to store nonce: @message', ['@message' => $e->getMessage()]);
      throw $e;
    }
  }

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
  public function verifyNonce(string $nonce, string $walletAddress): bool {
    try {
      $store = $this->tempStoreFactory->get('wallet_auth');
      $data = $store->get($nonce);

      if ($data === NULL) {
        $this->logger->warning('Nonce not found in storage');
        return FALSE;
      }

      $currentTime = $this->time->getRequestTime();
      $age = $currentTime - $data['created'];

      if ($age > self::NONCE_LIFETIME) {
        $this->logger->warning('Nonce expired (age: @age seconds)', ['@age' => $age]);
        $store->delete($nonce);
        return FALSE;
      }

      if ($data['wallet_address'] !== $walletAddress) {
        $this->logger->warning('Nonce wallet address mismatch');
        return FALSE;
      }

      return TRUE;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to verify nonce: @message', ['@message' => $e->getMessage()]);
      return FALSE;
    }
  }

  /**
   * Verify an Ethereum signature matches the expected address.
   *
   * This implements EIP-191 (personal_sign) signature verification.
   * The message is prefixed with "\x19Ethereum Signed Message:\n" before
   * being hashed with Keccak-256.
   *
   * @param string $message
   *   The original message that was signed.
   * @param string $signature
   *   The hex signature (0x-prefixed).
   * @param string $walletAddress
   *   The expected wallet address (0x-prefixed, checksummed).
   *
   * @return bool
   *   TRUE if signature is valid for the address, FALSE otherwise.
   */
  public function verifySignature(string $message, string $signature, string $walletAddress): bool {
    try {
      // Validate inputs.
      if (!$this->validateAddress($walletAddress)) {
        $this->logger->warning('Invalid wallet address format');
        return FALSE;
      }

      if (!str_starts_with($signature, '0x')) {
        $this->logger->warning('Signature must be 0x-prefixed');
        return FALSE;
      }

      // Convert hex signature to binary.
      $signatureBin = hex2bin(substr($signature, 2));
      if ($signatureBin === FALSE) {
        $this->logger->warning('Invalid hex signature format');
        return FALSE;
      }

      // Extract r, s, v from signature (65 bytes total)
      if (strlen($signatureBin) !== 65) {
        $this->logger->warning('Signature must be 65 bytes');
        return FALSE;
      }

      $r = substr($signatureBin, 0, 32);
      $s = substr($signatureBin, 32, 32);
      $v = ord(substr($signatureBin, 64, 1));

      $this->logger->debug('Raw signature values: r_len=@r_len, s_len=@s_len, v=@v', [
        '@r_len' => strlen($r),
        '@s_len' => strlen($s),
        '@v' => $v,
      ]);

      // Normalize v for EIP-155 signatures.
      // EIP-155 uses v = chainId * 2 + 35 or chainId * 2 + 36
      // For personal_sign (EIP-191), we need v to be 27 or 28.
      // If v >= 35, it's likely an EIP-155 signature that needs conversion.
      if ($v >= 35) {
        // Convert from EIP-155 v to EIP-191 v (27 or 28)
        // Formula: v = chainId * 2 + 35 + recovery_id (0 or 1)
        // So: recovery_id = (v - 35) % 2, then v = 27 + recovery_id
        $v = 27 + (($v - 35) % 2);

        $this->logger->debug('Normalized EIP-155 v to @v', ['@v' => $v]);
      }
      elseif ($v < 27) {
        $v += 27;
      }

      // Validate v is in expected range (27-30 for recovery)
      if ($v < 27 || $v > 30) {
        $this->logger->warning('Invalid v value after normalization: @v', ['@v' => $v]);
        return FALSE;
      }

      // Add Ethereum signed message prefix.
      $prefixedMessage = "\x19Ethereum Signed Message:\n" . strlen($message) . $message;
      $hash = Keccak::hash($prefixedMessage, 256, TRUE);

      // Recover public key from signature.
      // elliptic-php expects v to be 0-3 (recovery ID), not 27-30.
      $recoveryId = $v - 27;
      $ec = new EC('secp256k1');
      $pubKey = $ec->recoverPubKey($hash, ['r' => $r, 's' => $s], $recoveryId);

      if ($pubKey === NULL) {
        $this->logger->warning('Failed to recover public key from signature');
        return FALSE;
      }

      // Derive address from public key.
      $recoveredAddress = $this->pubKeyToAddress($pubKey);

      // Compare recovered address with expected address (case-insensitive)
      $isValid = strtolower($recoveredAddress) === strtolower($walletAddress);

      if (!$isValid) {
        $this->logger->warning('Signature verification failed: address mismatch');
      }
      else {
        $this->logger->info('Signature verified successfully for @wallet', ['@wallet' => $walletAddress]);
      }

      return $isValid;
    }
    catch (\Exception $e) {
      $this->logger->error('Signature verification error: @message', ['@message' => $e->getMessage()]);
      return FALSE;
    }
  }

  /**
   * Convert a public key to an Ethereum address.
   *
   * @param mixed $pubKey
   *   The public key object from elliptic-php.
   *
   * @return string
   *   The 0x-prefixed Ethereum address.
   */
  protected function pubKeyToAddress($pubKey): string {
    // Get the uncompressed public key (remove first byte: 0x04)
    $pubKeyHex = $pubKey->encode('hex');
    $pubKeyBin = hex2bin(substr($pubKeyHex, 2));

    // Hash the public key with Keccak-256.
    $hash = Keccak::hash($pubKeyBin, 256, TRUE);

    // Take the last 20 bytes as the address.
    $address = substr($hash, -20);

    // Return 0x-prefixed hex address.
    return '0x' . bin2hex($address);
  }

  /**
   * Validate an Ethereum address format and checksum.
   *
   * @param string $address
   *   The address to validate.
   *
   * @return bool
   *   TRUE if address is valid, FALSE otherwise.
   */
  public function validateAddress(string $address): bool {
    // Check basic format: 0x prefix, 42 characters total, hex characters.
    if (!str_starts_with($address, '0x')) {
      return FALSE;
    }

    if (strlen($address) !== 42) {
      return FALSE;
    }

    $hexPart = substr($address, 2);
    if (!ctype_xdigit($hexPart)) {
      return FALSE;
    }

    // If all uppercase or all lowercase, it's valid.
    if (strtoupper($hexPart) === $hexPart || strtolower($hexPart) === $hexPart) {
      return TRUE;
    }

    // Validate checksum (EIP-55)
    return $this->validateChecksum($address);
  }

  /**
   * Validate EIP-55 mixed-case checksum.
   *
   * @param string $address
   *   The address to validate.
   *
   * @return bool
   *   TRUE if checksum is valid, FALSE otherwise.
   */
  protected function validateChecksum(string $address): bool {
    $address = substr($address, 2);
    $addressHash = Keccak::hash(strtolower($address), 256);

    for ($i = 0; $i < 40; $i++) {
      // The nth character should be uppercase if the nth character of
      // addressHash is >= 8.
      $char = $address[$i];
      $hashChar = hexdec($addressHash[$i]);

      if (ctype_digit($char)) {
        continue;
      }

      if ($hashChar >= 8 && strtolower($char) === $char) {
        return FALSE;
      }

      if ($hashChar < 8 && strtoupper($char) === $char) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Delete a nonce from storage after use.
   *
   * @param string $nonce
   *   The nonce to delete.
   */
  public function deleteNonce(string $nonce): void {
    try {
      $store = $this->tempStoreFactory->get('wallet_auth');
      $store->delete($nonce);
      $this->logger->info('Deleted nonce after successful authentication');
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to delete nonce: @message', ['@message' => $e->getMessage()]);
    }
  }

}
