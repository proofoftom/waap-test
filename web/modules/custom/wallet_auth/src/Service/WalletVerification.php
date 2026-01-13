<?php

declare(strict_types=1);

namespace Drupal\wallet_auth\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\wallet_auth\WalletVerificationInterface;
use Elliptic\EC;
use kornrunner\Keccak;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Service for wallet signature verification and nonce management.
 */
class WalletVerification implements WalletVerificationInterface {

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
  protected \Drupal\Core\Logger\LoggerChannelInterface $logger;

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
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Default nonce lifetime in seconds (5 minutes).
   *
   * @var int
   */
  protected const DEFAULT_NONCE_LIFETIME = 300;

  /**
   * Chain ID mapping for supported networks.
   *
   * @var array
   */
  protected const CHAIN_IDS = [
    'mainnet' => 1,
    'sepolia' => 11155111,
    'polygon' => 137,
    'bsc' => 56,
    'arbitrum' => 42161,
    'optimism' => 10,
  ];

  /**
   * Constructs a WalletVerification service.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The private tempstore factory.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(
    Connection $database,
    LoggerChannelFactoryInterface $logger_factory,
    TimeInterface $time,
    PrivateTempStoreFactory $temp_store_factory,
    ConfigFactoryInterface $config_factory,
    RequestStack $request_stack,
  ) {
    $this->database = $database;
    $this->logger = $logger_factory->get('wallet_auth');
    $this->time = $time;
    $this->tempStoreFactory = $temp_store_factory;
    $this->configFactory = $config_factory;
    $this->requestStack = $request_stack;
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

      // Use configurable nonce lifetime from settings, fallback to default.
      $lifetime = $this->configFactory->get('wallet_auth.settings')->get('nonce_lifetime') ?? self::DEFAULT_NONCE_LIFETIME;

      if ($age > $lifetime) {
        $this->logger->warning('Nonce expired (age: @age seconds, lifetime: @lifetime)', [
          '@age' => $age,
          '@lifetime' => $lifetime,
        ]);
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
  public function verifySignature(string $message, string $signature, string $walletAddress): bool {
    try {
      // Log the received message for debugging.
      $this->logger->debug('Received message for verification (length @len): @message', [
        '@len' => strlen($message),
        '@message' => $message,
      ]);
      // Validate inputs.
      if (!$this->validateAddress($walletAddress)) {
        $this->logger->warning('Invalid wallet address format');
        return FALSE;
      }

      if (!str_starts_with($signature, '0x')) {
        $this->logger->warning('Signature must be 0x-prefixed');
        return FALSE;
      }

      // Parse and validate SIWE message structure.
      $siweFields = $this->parseSiweMessage($message);
      if ($siweFields === NULL) {
        $this->logger->warning('Invalid SIWE message format');
        return FALSE;
      }

      $this->logger->debug('SIWE fields parsed: @fields', ['@fields' => json_encode($siweFields)]);

      // Validate the address in the message matches the expected address.
      if (strtolower($siweFields['address']) !== strtolower($walletAddress)) {
        $this->logger->warning('SIWE message address mismatch');
        return FALSE;
      }

      // Validate the domain matches the current request host (Issue #3).
      $currentRequest = $this->requestStack->getCurrentRequest();
      if ($currentRequest !== NULL) {
        $expectedHost = $currentRequest->getHost();
        if ($siweFields['domain'] !== $expectedHost) {
          $this->logger->warning('SIWE domain mismatch: expected @expected, got @actual', [
            '@expected' => $expectedHost,
            '@actual' => $siweFields['domain'],
          ]);
          return FALSE;
        }
      }

      // Validate the chain ID matches the configured network (Issue #2).
      if (isset($siweFields['chainId'])) {
        $messageChainId = (int) $siweFields['chainId'];
        $configuredNetwork = $this->configFactory->get('wallet_auth.settings')->get('network') ?? 'mainnet';
        $expectedChainId = $this->getChainIdForNetwork($configuredNetwork);

        if ($expectedChainId !== NULL && $messageChainId !== $expectedChainId) {
          $this->logger->warning('SIWE chain ID mismatch: expected @expected (@network), got @actual', [
            '@expected' => $expectedChainId,
            '@network' => $configuredNetwork,
            '@actual' => $messageChainId,
          ]);
          return FALSE;
        }
      }

      // Validate the nonce hasn't expired.
      if (isset($siweFields['expirationTime'])) {
        $expirationTime = strtotime($siweFields['expirationTime']);
        if ($expirationTime < $this->time->getRequestTime()) {
          $this->logger->warning('SIWE message has expired');
          return FALSE;
        }
      }

      // Validate the message wasn't issued in the future.
      if (isset($siweFields['issuedAt'])) {
        $issuedAt = strtotime($siweFields['issuedAt']);
        // Allow 30 seconds clock skew.
        if ($issuedAt > $this->time->getRequestTime() + 30) {
          $this->logger->warning('SIWE message issued in the future');
          return FALSE;
        }
      }

      // Validate "not before" time constraint (Issue #9).
      if (isset($siweFields['notBefore'])) {
        $notBefore = strtotime($siweFields['notBefore']);
        if ($notBefore > $this->time->getRequestTime()) {
          $this->logger->warning('SIWE message not yet valid (notBefore: @notBefore)', [
            '@notBefore' => $siweFields['notBefore'],
          ]);
          return FALSE;
        }
      }

      // Extract nonce for verification later.
      $nonce = $siweFields['nonce'] ?? '';
      if (empty($nonce)) {
        $this->logger->warning('SIWE message missing nonce');
        return FALSE;
      }

      // Verify the nonce exists and is valid.
      if (!$this->verifyNonce($nonce, $walletAddress)) {
        $this->logger->warning('Invalid or expired nonce in SIWE message');
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

      // Normalize v to recovery ID (0-3).
      // Different wallets use different v formats:
      // - Standard Ethereum: v = 27 + recovery_id (27 or 28)
      // - EIP-155: v = chainId * 2 + 35 + recovery_id (e.g., 37, 38
      //   for mainnet)
      // - Some SDKs: v = recovery_id directly (0 or 1)
      $recoveryId = $v;

      if ($v >= 35) {
        // EIP-155 signature: extract recovery ID from v.
        $recoveryId = ($v - 35) % 2;
        $this->logger->debug('Normalized EIP-155 v to recovery ID @id', ['@id' => $recoveryId]);
      }
      elseif ($v >= 27) {
        // Standard Ethereum signature: v = 27 + recovery_id.
        $recoveryId = $v - 27;
        $this->logger->debug('Normalized Ethereum v to recovery ID @id', ['@id' => $recoveryId]);
      }
      // else: v is already the recovery ID (0-3 range)
      // Validate recovery ID is in valid range (0-3).
      if ($recoveryId < 0 || $recoveryId > 3) {
        $this->logger->warning('Invalid recovery ID: @id', ['@id' => $recoveryId]);
        return FALSE;
      }

      // Add Ethereum signed message prefix.
      $prefixedMessage = "\x19Ethereum Signed Message:\n" . strlen($message) . $message;
      $hash = Keccak::hash($prefixedMessage, 256, TRUE);

      $this->logger->debug('Message hash for signature recovery: @hash', [
        '@hash' => '0x' . bin2hex($hash),
      ]);

      // Convert binary signature components to hex for elliptic-php.
      // The library expects hex strings, not binary data.
      $rHex = bin2hex($r);
      $sHex = bin2hex($s);
      $hashHex = bin2hex($hash);

      // Recover public key from signature using the recovery ID.
      $ec = new EC('secp256k1');
      $pubKey = $ec->recoverPubKey($hashHex, ['r' => $rHex, 's' => $sHex], $recoveryId);

      if ($pubKey === NULL) {
        $this->logger->warning('Failed to recover public key from signature');
        return FALSE;
      }

      // Derive address from public key.
      $recoveredAddress = $this->pubKeyToAddress($pubKey);

      // Compare recovered address with expected address (case-insensitive)
      $isValid = strtolower($recoveredAddress) === strtolower($walletAddress);

      if (!$isValid) {
        $this->logger->warning('Signature verification failed: address mismatch. Expected: @expected, Recovered: @recovered', [
          '@expected' => $walletAddress,
          '@recovered' => $recoveredAddress,
        ]);
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
   * Parse a Sign-In with Ethereum (EIP-4361) message.
   *
   * @param string $message
   *   The SIWE message to parse.
   *
   * @return array|null
   *   Associative array of SIWE fields, or NULL if parsing fails.
   */
  protected function parseSiweMessage(string $message): ?array {
    // SIWE format:
    // <domain> wants you to sign in with your Ethereum account:
    // <address>
    //
    // <statement>
    //
    // URI: <uri>
    // Version: <version>
    // Chain ID: <chainId>
    // Nonce: <nonce>
    // Issued At: <issuedAt>
    // Expiration Time: <expirationTime>
    // Not Before: <notBefore>
    // Request ID: <requestId>
    // Resources:
    // - <resource1>
    // - <resource2>.
    $fields = [];
    $lines = explode("\n", $message);

    // Parse domain and address from the header.
    if (count($lines) < 3) {
      return NULL;
    }

    // First line: "<domain> wants you to sign in with your Ethereum account:".
    $domainMatch = [];
    if (!preg_match('/^(.+?) wants you to sign in with your Ethereum account:$/', $lines[0], $domainMatch)) {
      return NULL;
    }
    $fields['domain'] = trim($domainMatch[1]);

    // Second line: "<address>".
    $address = trim($lines[1]);
    if (!$this->validateAddress($address)) {
      return NULL;
    }
    $fields['address'] = $address;

    // Find the statement (between address and first field).
    $statementEnd = 2;
    $statementParts = [];
    for ($i = 2; $i < count($lines); $i++) {
      $line = trim($lines[$i]);
      // Stop at first field (contains colon followed by space).
      if (preg_match('/^[A-Za-z\s]+:\s*.+$/', $line)) {
        break;
      }
      if ($line !== '') {
        $statementParts[] = $line;
      }
      $statementEnd = $i;
    }
    if (!empty($statementParts)) {
      $fields['statement'] = implode("\n", $statementParts);
    }

    // Parse the remaining fields.
    for ($i = $statementEnd; $i < count($lines); $i++) {
      $line = trim($lines[$i]);
      if ($line === '') {
        continue;
      }

      // Handle continuation lines (resources).
      if (str_starts_with($line, '- ')) {
        if (!isset($fields['resources'])) {
          $fields['resources'] = [];
        }
        $fields['resources'][] = substr($line, 2);
        continue;
      }

      // Parse key-value pairs.
      if (strpos($line, ':') !== FALSE) {
        [$key, $value] = explode(':', $line, 2);
        $key = trim($key);
        $value = trim($value);

        // Convert key to camelCase.
        $camelKey = lcfirst(str_replace(' ', '', ucwords(str_replace('-', ' ', strtolower($key)))));
        $fields[$camelKey] = $value;
      }
    }

    // Validate required fields.
    $required = ['domain', 'address', 'uri', 'version', 'nonce', 'issuedAt'];
    foreach ($required as $field) {
      if (!isset($fields[$field]) || $fields[$field] === '') {
        return NULL;
      }
    }

    return $fields;
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

  /**
   * Get the chain ID for a given network name.
   *
   * @param string $network
   *   The network name (e.g., 'mainnet', 'sepolia', 'polygon').
   *
   * @return int|null
   *   The chain ID, or NULL if the network is not recognized.
   */
  protected function getChainIdForNetwork(string $network): ?int {
    return self::CHAIN_IDS[$network] ?? NULL;
  }

}
