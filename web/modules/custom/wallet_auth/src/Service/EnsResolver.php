<?php

namespace Drupal\wallet_auth\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use kornrunner\Keccak;
use Psr\Log\LoggerInterface;

/**
 * Service for resolving ENS names to/from Ethereum addresses.
 *
 * Uses HTTP-based JSON-RPC calls to Ethereum nodes for ENS resolution.
 * Supports both forward resolution (name → address) and reverse resolution
 * (address → name) with caching and automatic RPC failover.
 */
class EnsResolver implements EnsResolverInterface {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected ClientInterface $httpClient;

  /**
   * Array of RPC provider URLs.
   *
   * @var array
   */
  protected array $providerUrls;

  /**
   * Current provider index for failover.
   *
   * @var int
   */
  protected int $currentProviderIndex = 0;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected CacheBackendInterface $cache;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * Cache TTL in seconds.
   *
   * @var int
   */
  protected int $cacheTtl;

  /**
   * ENS Registry contract address.
   */
  const ENS_REGISTRY_ADDRESS = '0x00000000000C2E074eC69A0dFb2997BA6C7d2e1e';

  /**
   * Cache key prefix.
   */
  const CACHE_PREFIX = 'wallet_auth:ens';

  /**
   * Function selector for resolver(bytes32).
   *
   * keccak256("resolver(bytes32)")[:4] = 0x0178b8bf
   */
  const RESOLVER_SELECTOR = '0x0178b8bf';

  /**
   * Function selector for addr(bytes32).
   *
   * keccak256("addr(bytes32)")[:4] = 0x3b3b57de
   */
  const ADDR_SELECTOR = '0x3b3b57de';

  /**
   * Function selector for name(bytes32).
   *
   * keccak256("name(bytes32)")[:4] = 0x691f3431
   */
  const NAME_SELECTOR = '0x691f3431';

  /**
   * Constructs an EnsResolver object.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\wallet_auth\Service\RpcProviderManager $provider_manager
   *   The RPC provider manager.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param int $cache_ttl
   *   Cache TTL in seconds (default: 3600).
   */
  public function __construct(
    ClientInterface $http_client,
    RpcProviderManager $provider_manager,
    CacheBackendInterface $cache,
    LoggerChannelFactoryInterface $logger_factory,
    int $cache_ttl = 3600,
  ) {
    $this->httpClient = $http_client;
    $this->providerUrls = $provider_manager->getProviderUrls();
    $this->cache = $cache;
    $this->logger = $logger_factory->get('wallet_auth');
    $this->cacheTtl = $cache_ttl;
  }

  /**
   * Makes an eth_call JSON-RPC request.
   *
   * @param string $to
   *   The contract address.
   * @param string $data
   *   The call data (function selector + encoded parameters).
   *
   * @return string|null
   *   The result data or NULL on failure.
   *
   * @throws \Exception
   *   If all providers fail.
   */
  protected function ethCall(string $to, string $data): ?string {
    $lastException = NULL;
    $startIndex = $this->currentProviderIndex;

    do {
      try {
        $response = $this->httpClient->request('POST', $this->providerUrls[$this->currentProviderIndex], [
          'json' => [
            'jsonrpc' => '2.0',
            'method' => 'eth_call',
            'params' => [
              [
                'to' => $to,
                'data' => $data,
              ],
              'latest',
            ],
            'id' => 1,
          ],
          'headers' => [
            'Content-Type' => 'application/json',
          ],
          'timeout' => 10,
        ]);

        $body = json_decode($response->getBody()->getContents(), TRUE);

        if (isset($body['error'])) {
          throw new \Exception($body['error']['message'] ?? 'RPC error');
        }

        // Reset provider index if we had to failover.
        if ($this->currentProviderIndex !== $startIndex) {
          $this->currentProviderIndex = 0;
        }

        return $body['result'] ?? NULL;
      }
      catch (GuzzleException $e) {
        $lastException = $e;
        $this->logger->warning('RPC call failed on @url: @message', [
          '@url' => $this->providerUrls[$this->currentProviderIndex],
          '@message' => $e->getMessage(),
        ]);
      }
      catch (\Exception $e) {
        $lastException = $e;
        $this->logger->warning('RPC call failed on @url: @message', [
          '@url' => $this->providerUrls[$this->currentProviderIndex],
          '@message' => $e->getMessage(),
        ]);
      }
    } while ($this->tryNextProvider());

    // Reset for next call.
    $this->currentProviderIndex = 0;
    throw $lastException ?? new \Exception('All RPC providers failed');
  }

  /**
   * Tries the next provider in the list.
   *
   * @return bool
   *   TRUE if there's another provider to try, FALSE otherwise.
   */
  protected function tryNextProvider(): bool {
    $this->currentProviderIndex++;
    if ($this->currentProviderIndex < count($this->providerUrls)) {
      $this->logger->warning('Switching to fallback RPC provider: @url', [
        '@url' => $this->providerUrls[$this->currentProviderIndex],
      ]);
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function resolveName(string $ens_name): ?string {
    // Check cache first.
    $cache_key = self::CACHE_PREFIX . ':forward:' . strtolower($ens_name);
    $cached = $this->cache->get($cache_key);
    if ($cached !== FALSE) {
      return $cached->data;
    }

    try {
      $address = $this->doForwardResolution($ens_name);

      // Cache result (including NULL to prevent repeated lookups).
      $this->cache->set($cache_key, $address, time() + $this->cacheTtl);

      return $address;
    }
    catch (\Exception $e) {
      $this->logger->error('ENS forward resolution failed for @name: @message', [
        '@name' => $ens_name,
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Performs the actual forward resolution.
   *
   * @param string $ens_name
   *   The ENS name to resolve.
   *
   * @return string|null
   *   The resolved Ethereum address or NULL.
   */
  protected function doForwardResolution(string $ens_name): ?string {
    // Convert ENS name to node hash.
    $node = $this->namehash($ens_name);

    // Get resolver address from ENS Registry.
    $resolver_address = $this->getResolver($node);

    // Check if resolver exists.
    if (empty($resolver_address) || $this->isZeroAddress($resolver_address)) {
      return NULL;
    }

    // Get Ethereum address from resolver.
    return $this->getAddressFromResolver($resolver_address, $node);
  }

  /**
   * {@inheritdoc}
   */
  public function resolveAddress(string $address): ?string {
    // Normalize address (lowercase, ensure 0x prefix).
    $address = strtolower($address);
    if (substr($address, 0, 2) !== '0x') {
      $address = '0x' . $address;
    }

    // Check cache first.
    $cache_key = self::CACHE_PREFIX . ':reverse:' . $address;
    $cached = $this->cache->get($cache_key);
    if ($cached !== FALSE) {
      return $cached->data;
    }

    try {
      $ens_name = $this->doReverseResolution($address);

      // Verify forward resolution matches (security check).
      if ($ens_name !== NULL) {
        $forward_address = $this->resolveName($ens_name);
        if (empty($forward_address) || strtolower($forward_address) !== strtolower($address)) {
          $this->logger->warning('ENS forward verification failed: @ens resolves to @forward, not @address', [
            '@ens' => $ens_name,
            '@forward' => $forward_address ?? 'NULL',
            '@address' => $address,
          ]);
          $ens_name = NULL;
        }
      }

      // Cache result (including NULL to avoid repeated lookups).
      $this->cache->set($cache_key, $ens_name, time() + $this->cacheTtl);

      return $ens_name;
    }
    catch (\Exception $e) {
      $this->logger->error('ENS reverse resolution failed for @address: @message', [
        '@address' => $address,
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Performs the actual reverse resolution RPC call.
   *
   * @param string $address
   *   The Ethereum address (normalized, with 0x prefix).
   *
   * @return string|null
   *   The ENS name or NULL if not found.
   */
  protected function doReverseResolution(string $address): ?string {
    // Construct reverse node: <address>.addr.reverse
    // Remove 0x prefix for reverse name.
    $reverse_name = substr($address, 2) . '.addr.reverse';
    $node = $this->namehash($reverse_name);

    // Get resolver for the reverse node.
    $resolver_address = $this->getResolver($node);

    if (empty($resolver_address) || $this->isZeroAddress($resolver_address)) {
      return NULL;
    }

    // Call name() on the resolver.
    return $this->getNameFromResolver($resolver_address, $node);
  }

  /**
   * Gets the resolver address for a node from the ENS Registry.
   *
   * @param string $node
   *   The node hash (bytes32).
   *
   * @return string|null
   *   The resolver address or NULL if not found.
   */
  protected function getResolver(string $node): ?string {
    // Build call data: resolver(bytes32)
    $data = self::RESOLVER_SELECTOR . substr($node, 2);

    $result = $this->ethCall(self::ENS_REGISTRY_ADDRESS, $data);

    if (empty($result) || strlen($result) < 66) {
      return NULL;
    }

    // Extract address from result (last 40 chars of 64-char padded response).
    return '0x' . substr($result, -40);
  }

  /**
   * Gets the Ethereum address from a resolver contract.
   *
   * @param string $resolver_address
   *   The resolver contract address.
   * @param string $node
   *   The node hash.
   *
   * @return string|null
   *   The Ethereum address or NULL if not found.
   */
  protected function getAddressFromResolver(string $resolver_address, string $node): ?string {
    // Build call data: addr(bytes32)
    $data = self::ADDR_SELECTOR . substr($node, 2);

    $result = $this->ethCall($resolver_address, $data);

    if (empty($result) || strlen($result) < 66) {
      return NULL;
    }

    // Extract address from result.
    $address = '0x' . substr($result, -40);

    // Check if zero address.
    if ($this->isZeroAddress($address)) {
      return NULL;
    }

    return $address;
  }

  /**
   * Gets the ENS name from a reverse resolver contract.
   *
   * @param string $resolver_address
   *   The resolver contract address.
   * @param string $node
   *   The node hash.
   *
   * @return string|null
   *   The ENS name or NULL if not found.
   */
  protected function getNameFromResolver(string $resolver_address, string $node): ?string {
    // Build call data: name(bytes32)
    $data = self::NAME_SELECTOR . substr($node, 2);

    $result = $this->ethCall($resolver_address, $data);

    if (empty($result) || $result === '0x') {
      return NULL;
    }

    // Decode the string result (ABI-encoded string).
    return $this->decodeString($result);
  }

  /**
   * Decodes an ABI-encoded string from hex.
   *
   * @param string $hex
   *   The hex-encoded result.
   *
   * @return string|null
   *   The decoded string or NULL.
   */
  protected function decodeString(string $hex): ?string {
    // Remove 0x prefix.
    $hex = substr($hex, 2);

    // ABI string encoding: offset (32 bytes) + length (32 bytes) + data.
    if (strlen($hex) < 128) {
      return NULL;
    }

    // Read offset (first 32 bytes, should be 0x20 = 32).
    // Read length (next 32 bytes after offset).
    $length = hexdec(substr($hex, 64, 64));

    if ($length === 0) {
      return NULL;
    }

    // Read string data.
    $string_hex = substr($hex, 128, $length * 2);

    // Convert hex to string.
    $string = '';
    for ($i = 0; $i < strlen($string_hex); $i += 2) {
      $byte = hexdec(substr($string_hex, $i, 2));
      if ($byte === 0) {
        break;
      }
      $string .= chr($byte);
    }

    return !empty($string) ? $string : NULL;
  }

  /**
   * Checks if an address is the zero address.
   *
   * @param string $address
   *   The address to check.
   *
   * @return bool
   *   TRUE if zero address, FALSE otherwise.
   */
  protected function isZeroAddress(string $address): bool {
    return strtolower($address) === '0x0000000000000000000000000000000000000000';
  }

  /**
   * Converts an ENS name to a node hash using the namehash algorithm.
   *
   * @param string $name
   *   The ENS name.
   *
   * @return string
   *   The node hash (0x-prefixed, 66 chars).
   */
  protected function namehash(string $name): string {
    if (empty($name)) {
      return '0x0000000000000000000000000000000000000000000000000000000000000000';
    }

    $node = str_repeat('0', 64);

    // Split the name into labels and process in reverse order.
    $labels = explode('.', strtolower($name));
    $labels = array_reverse($labels);

    foreach ($labels as $label) {
      $label_hash = Keccak::hash($label, 256);
      $node = Keccak::hash(hex2bin($node) . hex2bin($label_hash), 256);
    }

    return '0x' . $node;
  }

  /**
   * {@inheritdoc}
   */
  public function clearCache(string $identifier): void {
    // Normalize identifier.
    $identifier = strtolower($identifier);

    // Clear both forward and reverse cache entries.
    $this->cache->delete(self::CACHE_PREFIX . ':forward:' . $identifier);
    $this->cache->delete(self::CACHE_PREFIX . ':reverse:' . $identifier);
  }

}
