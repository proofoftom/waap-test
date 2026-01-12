/**
 * @file
 * Wallet authentication UI integration with Drupal behaviors.
 */

(function ($, Drupal, drupalSettings) {
  'use strict';

  /**
   * Wallet authentication behavior.
   */
  Drupal.behaviors.walletAuth = {
    connector: null,
    state: 'idle', // idle, connecting, connected, signing, error

    attach: function (context, settings) {
      // Only attach once
      if ($('body').once('wallet-auth-init').length) {
        this.init(context, settings);
      }
    },

    /**
     * Initialize wallet authentication.
     */
    init: function (context, settings) {
      const self = this;

      // Get configuration from drupalSettings
      const config = settings.walletAuth || {};

      // Initialize connector
      this.connector = new WalletAuthConnector({
        authenticationMethods: config.authenticationMethods || ['email', 'social'],
        allowedSocials: config.allowedSocials || ['google', 'twitter', 'discord'],
      });

      // Bind login button
      const $loginButton = $('.wallet-auth-login-btn', context);
      $loginButton.on('click', function (e) {
        e.preventDefault();
        self.handleLogin();
      });

      // Bind disconnect button
      const $disconnectButton = $('.wallet-auth-disconnect-btn', context);
      $disconnectButton.on('click', function (e) {
        e.preventDefault();
        self.handleDisconnect();
      });

      // Initialize connector and check for existing session
      this.connector.init().then(() => {
        return this.connector.checkSession();
      }).then((account) => {
        if (account) {
          // User already authenticated
          self.setState('connected');
          self.updateUI();
          // Auto-proceed with authentication
          self.authenticate(account);
        } else {
          // Show login button
          self.setState('idle');
          self.updateUI();
        }
      }).catch((error) => {
        console.error('Initialization error:', error);
        self.setState('error');
        self.showError(error.message);
      });

      // Listen for disconnect events
      this.connector.on('disconnect', () => {
        self.setState('idle');
        self.updateUI();
      });

      // Listen for account changes
      this.connector.on('accountChanged', (accounts) => {
        if (accounts.length > 0) {
          self.authenticate(accounts[0]);
        } else {
          self.setState('idle');
          self.updateUI();
        }
      });
    },

    /**
     * Handle login button click.
     */
    handleLogin: function () {
      const self = this;

      this.setState('connecting');

      this.connector.login().then((loginType) => {
        if (!loginType) {
          // User cancelled
          this.setState('idle');
          return;
        }

        console.log('Logged in via:', loginType);
        this.setState('connected');

        // Proceed with authentication
        const address = this.connector.getAddress();
        this.authenticate(address);

      }).catch((error) => {
        console.error('Login error:', error);
        this.setState('error');
        this.showError(error.message);
      });
    },

    /**
     * Handle disconnect button click.
     */
    handleDisconnect: function () {
      this.connector.logout();
      this.setState('idle');
      this.updateUI();
    },

    /**
     * Complete authentication flow: fetch nonce, sign, verify.
     */
    authenticate: function (address) {
      const self = this;

      this.setState('signing');
      this.updateUI();

      // Step 1: Fetch nonce from backend
      this.fetchNonce(address).then((data) => {
        const nonce = data.nonce;
        this.connector.lastNonce = nonce;

        // Step 2: Create message to sign
        const message = this.createSignMessage(address, nonce);

        // Step 3: Request signature from wallet
        return this.connector.signMessage(message);

      }).then((signature) => {
        // Step 4: Send signature to backend for verification
        return this.sendAuthentication(address, signature);

      }).then((response) => {
        if (response.success) {
          // Authentication successful
          this.setState('authenticated');
          this.showSuccess(`Logged in as ${response.username}`);
          // Optionally redirect or update page
          if (drupalSettings.walletAuth.redirectOnSuccess) {
            window.location.href = drupalSettings.walletAuth.redirectOnSuccess;
          } else {
            window.location.reload();
          }
        } else {
          throw new Error(response.error || 'Authentication failed');
        }

      }).catch((error) => {
        console.error('Authentication error:', error);
        this.setState('error');
        this.showError(error.message || 'Authentication failed');
      });
    },

    /**
     * Fetch nonce from backend.
     */
    fetchNonce: function (address) {
      const apiEndpoint = drupalSettings.walletAuth.apiEndpoint;

      return fetch(`${apiEndpoint}/nonce?wallet_address=${address}`, {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
      }).then((response) => {
        if (!response.ok) {
          throw new Error('Failed to fetch nonce');
        }
        return response.json();
      });
    },

    /**
     * Create message for signing.
     *
     * Uses simple format compatible with backend EIP-191 verification.
     */
    createSignMessage: function (address, nonce) {
      return `Sign this message to prove ownership of ${address}.\n\nNonce: ${nonce}`;
    },

    /**
     * Send authentication data to backend.
     */
    sendAuthentication: function (address, signature) {
      const apiEndpoint = drupalSettings.walletAuth.apiEndpoint;
      const nonce = this.connector.lastNonce; // Store this when fetching

      const message = this.createSignMessage(address, nonce);

      return fetch(apiEndpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          wallet_address: address,
          signature: signature,
          message: message,
          nonce: nonce,
        }),
      }).then((response) => {
        if (!response.ok) {
          return response.json().then((data) => {
            throw new Error(data.error || 'Authentication failed');
          });
        }
        return response.json();
      });
    },

    /**
     * Set current state.
     */
    setState: function (state) {
      this.state = state;
      $('body').attr('data-wallet-auth-state', state);
    },

    /**
     * Update UI based on current state.
     */
    updateUI: function () {
      const $container = $('.wallet-auth-container');
      const $loginButton = $('.wallet-auth-login-btn');
      const $disconnectButton = $('.wallet-auth-disconnect-btn');
      const $status = $('.wallet-auth-status');

      // Hide all by default
      $loginButton.addClass('visually-hidden');
      $disconnectButton.addClass('visually-hidden');

      switch (this.state) {
        case 'idle':
          $loginButton.removeClass('visually-hidden');
          $status.text('Connect your wallet to login');
          break;

        case 'connecting':
          $loginButton.removeClass('visually-hidden').prop('disabled', true);
          $status.text('Connecting to wallet...');
          break;

        case 'connected':
          $disconnectButton.removeClass('visually-hidden');
          $status.text(`Connected: ${this.formatAddress(this.connector.getAddress())}`);
          break;

        case 'signing':
          $status.text('Please sign the message in your wallet...');
          break;

        case 'authenticated':
          $status.text('Authentication successful!');
          break;

        case 'error':
          $loginButton.removeClass('visually-hidden').prop('disabled', false);
          $status.text('Authentication failed. Please try again.');
          break;
      }
    },

    /**
     * Show error message.
     */
    showError: function (message) {
      // Use Drupal messages or custom UI
      if (Drupal.behaviors.walletAuth.showMessage) {
        Drupal.behaviors.walletAuth.showMessage(message, 'error');
      } else {
        alert(message);
      }
    },

    /**
     * Show success message.
     */
    showSuccess: function (message) {
      if (Drupal.behaviors.walletAuth.showMessage) {
        Drupal.behaviors.walletAuth.showMessage(message, 'status');
      } else {
        console.log(message);
      }
    },

    /**
     * Format address for display.
     */
    formatAddress: function (address) {
      if (!address) return '';
      return `${address.substring(0, 6)}...${address.substring(address.length - 4)}`;
    },

    /**
     * Detach behavior (cleanup).
     */
    detach: function (context, settings) {
      if (this.connector) {
        this.connector.destroy();
      }
    },
  };

  /**
   * Helper to show Drupal messages.
   */
  Drupal.behaviors.walletAuth.showMessage = function (message, type = 'status') {
    // Add message to Drupal's message container
    const $messages = $('.messages__wrapper', context);
    if ($messages.length) {
      const $message = $(`<div class="messages messages--${type}">${message}</div>`);
      $messages.append($message);
      // Auto-remove after 5 seconds
      setTimeout(() => $message.fadeOut(), 5000);
    }
  };

})(jQuery, Drupal, drupalSettings);
