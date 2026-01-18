<?php

namespace Drupal\wallet_auth\Service;

/**
 * Interface for ENS name resolution services.
 *
 * Provides forward and reverse resolution for Ethereum Name Service (ENS).
 */
interface EnsResolverInterface {

  /**
   * Resolves an ENS name to an Ethereum address (forward resolution).
   *
   * @param string $ens_name
   *   The ENS name to resolve (e.g., "vitalik.eth").
   *
   * @return string|null
   *   The resolved Ethereum address (checksummed) or NULL if resolution fails.
   */
  public function resolveName(string $ens_name): ?string;

  /**
   * Resolves an Ethereum address to its primary ENS name (reverse resolution).
   *
   * Includes forward verification to ensure the resolved name points back
   * to the original address (security check against spoofing).
   *
   * @param string $address
   *   The Ethereum address (with or without 0x prefix).
   *
   * @return string|null
   *   The primary ENS name or NULL if not found/not set.
   */
  public function resolveAddress(string $address): ?string;

  /**
   * Clears the ENS cache for a specific name or address.
   *
   * @param string $identifier
   *   The ENS name or Ethereum address to clear from cache.
   */
  public function clearCache(string $identifier): void;

}
