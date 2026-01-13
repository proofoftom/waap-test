/**
 * @file
 * WaaP SDK wrapper for wallet authentication.
 */

import { initWaaP } from '@human.tech/waap-sdk';

(function () {
  'use strict';

  /**
   * Wallet connector service.
   *
   * Wraps WaaP SDK with Drupal-specific patterns and error handling.
   */
  class WalletConnector {
    constructor(config = {}) {
      this.config = {
        authenticationMethods: ['email', 'social'],
        allowedSocials: ['google', 'twitter', 'discord'],
        ...config,
      };
      this.provider = null;
      this.account = null;
      this.chainId = null;
      this.listeners = new Map();
      this.lastNonce = null;
    }

    /**
     * Initialize WaaP SDK.
     *
     * Must be called before any other methods.
     */
    async init() {
      if (this.provider) {
        return; // Already initialized
      }

      try {
        // Initialize WaaP SDK
        initWaaP({
          config: this.config,
          useStaging: false,
        });

        // Get provider
        this.provider = window.waap;

        if (!this.provider) {
          throw new Error('WaaP provider not available after initialization');
        }

        // Set up event listeners
        this.attachEventListeners();

        console.log('WaaP SDK initialized successfully');
      } catch (error) {
        console.error('Failed to initialize WaaP SDK:', error);
        throw error;
      }
    }

    /**
     * Attach EIP-1193 event listeners.
     */
    attachEventListeners() {
      if (!this.provider) return;

      // Connect event
      this.provider.on('connect', (connectInfo) => {
        this.chainId = connectInfo.chainId;
        this.notifyListeners('connect', connectInfo);
        console.log('Wallet connected:', connectInfo);
      });

      // Disconnect event
      this.provider.on('disconnect', (error) => {
        this.account = null;
        this.chainId = null;
        this.notifyListeners('disconnect', error);
        console.log('Wallet disconnected:', error);
      });

      // Account changed event
      this.provider.on('accountsChanged', (accounts) => {
        if (accounts.length === 0) {
          // User disconnected wallet
          this.account = null;
          this.notifyListeners('disconnect', null);
        } else {
          this.account = accounts[0];
          this.notifyListeners('accountChanged', accounts);
        }
        console.log('Accounts changed:', accounts);
      });

      // Chain changed event
      this.provider.on('chainChanged', (chainId) => {
        this.chainId = chainId;
        this.notifyListeners('chainChanged', chainId);
        // Recommended: reload page on chain change
        window.location.reload();
      });
    }

    /**
     * Check for existing session (auto-connect).
     *
     * Returns account if user is already authenticated, null otherwise.
     */
    async checkSession() {
      if (!this.provider) {
        await this.init();
      }

      try {
        const accounts = await this.provider.request({
          method: 'eth_requestAccounts',
        });

        if (accounts && accounts.length > 0) {
          this.account = accounts[0];
          console.log('Auto-connected with existing session:', this.account);
          return this.account;
        }

        return null;
      } catch (error) {
        // No existing session or user rejected
        console.log('No existing session found');
        return null;
      }
    }

    /**
     * Show WaaP login modal.
     *
     * Returns login type ('waap', 'injected', 'walletconnect', null).
     */
    async login() {
      if (!this.provider) {
        await this.init();
      }

      try {
        const loginType = await this.provider.login();
        console.log('Login type:', loginType);

        if (loginType) {
          // Get accounts after login
          const accounts = await this.provider.request({
            method: 'eth_requestAccounts',
          });
          this.account = accounts[0];
        }

        return loginType;
      } catch (error) {
        console.error('Login failed:', error);
        throw error;
      }
    }

    /**
     * Sign a message using personal_sign (EIP-191).
     *
     * @param {string} message
     *   The message to sign.
     *
     * @return {string}
     *   The signature (0x-prefixed hex).
     */
    async signMessage(message) {
      if (!this.provider || !this.account) {
        throw new Error('Wallet not connected');
      }

      try {
        const signature = await this.provider.request({
          method: 'personal_sign',
          params: [message, this.account],
        });

        console.log('Message signed successfully');
        return signature;
      } catch (error) {
        console.error('Message signing failed:', error);
        throw error;
      }
    }

    /**
     * Get current wallet address.
     */
    getAddress() {
      return this.account;
    }

    /**
     * Get current chain ID.
     */
    getChainId() {
      return this.chainId;
    }

    /**
     * Check if wallet is connected.
     */
    isConnected() {
      return !!this.account;
    }

    /**
     * Add event listener.
     */
    on(event, callback) {
      if (!this.listeners.has(event)) {
        this.listeners.set(event, []);
      }
      this.listeners.get(event).push(callback);
    }

    /**
     * Remove event listener.
     */
    off(event, callback) {
      if (!this.listeners.has(event)) return;
      const callbacks = this.listeners.get(event);
      const index = callbacks.indexOf(callback);
      if (index > -1) {
        callbacks.splice(index, 1);
      }
    }

    /**
     * Notify all listeners of an event.
     */
    notifyListeners(event, data) {
      if (!this.listeners.has(event)) return;
      this.listeners.get(event).forEach(callback => callback(data));
    }

    /**
     * Logout and disconnect wallet.
     */
    async logout() {
      if (!this.provider) return;

      try {
        await this.provider.logout();
        this.account = null;
        this.chainId = null;
        console.log('Logged out successfully');
      } catch (error) {
        console.error('Logout failed:', error);
      }
    }

    /**
     * Cleanup event listeners.
     */
    destroy() {
      if (this.provider) {
        this.provider.removeAllListeners();
      }
      this.listeners.clear();
      this.provider = null;
      this.account = null;
      this.chainId = null;
    }
  }

  // Create namespaced Drupal object for wallet auth
  // Access Drupal from global window object
  if (typeof window.Drupal !== 'undefined') {
    window.Drupal.walletAuth = window.Drupal.walletAuth || {};

    // Expose WalletConnector class through Drupal namespace
    window.Drupal.walletAuth.WalletConnector = WalletConnector;
  } else {
    console.error('Drupal is not available. WalletConnector cannot be initialized.');
  }

})();
