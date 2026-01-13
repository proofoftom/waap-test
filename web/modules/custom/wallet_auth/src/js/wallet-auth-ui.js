/**
 * @file
 * Wallet authentication UI integration with Drupal behaviors.
 */

import jQuery from 'jQuery';
import Drupal from 'Drupal';
import drupalSettings from 'drupalSettings';

'use strict';

// Flag to ensure we only initialize once
var walletAuthInitialized = false;

/**
 * Wallet authentication behavior.
 */
Drupal.behaviors.walletAuth = {
  connector: null,
  state: 'idle', // idle, connecting, connected, signing, error
  csrfToken: null, // Store CSRF token from nonce response

  attach: function (context, settings) {
    // Only attach once using a simple flag
    if (!walletAuthInitialized && jQuery(context).is(document)) {
      walletAuthInitialized = true;
      this.init(context, settings);
    }
  },

  /**
   * Initialize wallet authentication.
   */
  init: function (context, settings) {
    var self = this;
    var $ = jQuery;

    // Get configuration from drupalSettings
    var config = settings.walletAuth || {};

    // Initialize connector using namespaced class
    this.connector = new Drupal.walletAuth.WalletConnector({
      authenticationMethods: config.authenticationMethods || ['email', 'social'],
      allowedSocials: config.allowedSocials || ['google', 'twitter', 'discord'],
    });

    // Bind login button
    var $loginButton = $('.wallet-auth-login-btn', context);
    $loginButton.on('click', function (e) {
      e.preventDefault();
      self.handleLogin();
    });

    // Bind disconnect button
    var $disconnectButton = $('.wallet-auth-disconnect-btn', context);
    $disconnectButton.on('click', function (e) {
      e.preventDefault();
      self.handleDisconnect();
    });

    // Initialize connector and check for existing session
    this.connector.init().then(function () {
      return self.connector.checkSession();
    }).then(function (account) {
      if (account) {
        // User already authenticated with WaaP, but don't auto-login
        // Just update UI to show connected state
        self.setState('connected');
        self.updateUI();
      } else {
        // Show login button
        self.setState('idle');
        self.updateUI();
      }
    }).catch(function (error) {
      console.error('Initialization error:', error);
      self.setState('error');
      self.showError(error.message);
    });

    // Listen for disconnect events
    this.connector.on('disconnect', function () {
      self.setState('idle');
      self.updateUI();
    });

    // Listen for account changes
    this.connector.on('accountChanged', function (accounts) {
      if (accounts.length > 0) {
        // Account changed, but don't auto-authenticate
        // Just update UI to show connected state
        self.setState('connected');
        self.updateUI();
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
    var self = this;

    // Check if already connected to WaaP
    var existingAddress = this.connector.getAddress();
    if (existingAddress) {
      // Already connected, proceed directly to authentication
      console.log('Already connected to WaaP, proceeding to authentication');
      this.authenticate(existingAddress);
      return;
    }

    this.setState('connecting');

    this.connector.login().then(function (loginType) {
      if (!loginType) {
        // User cancelled
        self.setState('idle');
        return;
      }

      console.log('Logged in via:', loginType);
      self.setState('connected');

      // Proceed with authentication
      var address = self.connector.getAddress();
      self.authenticate(address);

    }).catch(function (error) {
      console.error('Login error:', error);
      self.setState('error');
      self.showError(error.message);
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
    var self = this;

    this.setState('signing');
    this.updateUI();

    // Step 1: Fetch nonce from backend
    this.fetchNonce(address).then(function (data) {
      var nonce = data.nonce;
      self.connector.lastNonce = nonce;
      // Store CSRF token for later use in authentication
      self.csrfToken = data.csrf_token;

      // Step 2: Create message to sign
      var message = self.createSignMessage(address, nonce);

      // Step 3: Request signature from wallet
      return self.connector.signMessage(message).then(function (signature) {
        // Return both signature and message for verification
        return { signature: signature, message: message, nonce: nonce };
      });

    }).then(function (authData) {
      // Step 4: Send signature and original message to backend for verification
      return self.sendAuthentication(address, authData.signature, authData.message, authData.nonce);

    }).then(function (response) {
      if (response.success) {
        // Authentication successful
        self.setState('authenticated');
        self.showSuccess('Logged in as ' + response.username);
        // Optionally redirect or update page
        if (drupalSettings.walletAuth.redirectOnSuccess) {
          window.location.href = drupalSettings.walletAuth.redirectOnSuccess;
        } else {
          window.location.reload();
        }
      } else {
        throw new Error(response.error || 'Authentication failed');
      }

    }).catch(function (error) {
      console.error('Authentication error:', error);

      // Check if user rejected the request
      if (error.message && error.message.includes('User rejected') ||
          error.message && error.message.includes('user rejected') ||
          error.code === 4001) {
        // User cancelled - reset to connected state so they can try again
        self.setState('connected');
        self.updateUI();
        self.showError('Signature request was cancelled. Please try again.');
      } else {
        // Actual error
        self.setState('error');
        self.updateUI();
        self.showError(error.message || 'Authentication failed');
      }
    });
  },

  /**
   * Fetch nonce from backend.
   */
  fetchNonce: function (address) {
    var apiEndpoint = drupalSettings.walletAuth.apiEndpoint;

    return fetch(apiEndpoint + '/nonce?wallet_address=' + address, {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
    }).then(function (response) {
      if (!response.ok) {
        throw new Error('Failed to fetch nonce');
      }
      return response.json();
    });
  },

  /**
   * Create message for signing.
   *
   * Generates a Sign-In with Ethereum (SIWE/EIP-4361) compliant message.
   */
  createSignMessage: function (address, nonce) {
    var domain = window.location.hostname;
    var uri = window.location.origin;
    var issuedAt = new Date().toISOString();
    var expirationTime = new Date(Date.now() + 300000).toISOString(); // 5 minutes
    var chainId = drupalSettings.walletAuth.chainId || 1;

    var message = domain + ' wants you to sign in with your Ethereum account:\n';
    message += address + '\n\n';
    message += 'Sign in with Ethereum to prove ownership of your wallet.\n\n';
    message += 'URI: ' + uri + '\n';
    message += 'Version: 1\n';
    message += 'Chain ID: ' + chainId + '\n';
    message += 'Nonce: ' + nonce + '\n';
    message += 'Issued At: ' + issuedAt + '\n';
    message += 'Expiration Time: ' + expirationTime;

    return message;
  },

  /**
   * Send authentication data to backend.
   *
   * @param {string} address
   *   The wallet address.
   * @param {string} signature
   *   The signature from the wallet.
   * @param {string} message
   *   The original message that was signed.
   * @param {string} nonce
   *   The nonce used in the message.
   */
  sendAuthentication: function (address, signature, message, nonce) {
    var apiEndpoint = drupalSettings.walletAuth.apiEndpoint + '/authenticate';

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
        csrf_token: this.csrfToken,
      }),
    }).then(function (response) {
      if (!response.ok) {
        return response.json().then(function (data) {
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
    jQuery('body').attr('data-wallet-auth-state', state);
  },

  /**
   * Update UI based on current state.
   */
  updateUI: function () {
    var $ = jQuery;
    var $container = $('.wallet-auth-container');
    var $loginButton = $('.wallet-auth-login-btn');
    var $disconnectButton = $('.wallet-auth-disconnect-btn');
    var $status = $('.wallet-auth-status');

    // Hide all by default
    $loginButton.addClass('visually-hidden');
    $disconnectButton.addClass('visually-hidden');

    switch (this.state) {
      case 'idle':
        $loginButton.removeClass('visually-hidden').find('span').text('Connect Wallet');
        $status.text('Connect your wallet to login');
        break;

      case 'connecting':
        $loginButton.removeClass('visually-hidden').prop('disabled', true).find('span').text('Connecting...');
        $status.text('Connecting to wallet...');
        break;

      case 'connected':
        // When connected but not authenticated, show both sign-in and disconnect
        $loginButton.removeClass('visually-hidden').prop('disabled', false).find('span').text('Sign in');
        $disconnectButton.removeClass('visually-hidden');
        $status.text('Connected: ' + this.formatAddress(this.connector.getAddress()));
        break;

      case 'signing':
        $loginButton.removeClass('visually-hidden').prop('disabled', true).find('span').text('Signing...');
        $status.text('Please sign the message in your wallet...');
        break;

      case 'authenticated':
        $status.text('Authentication successful!');
        break;

      case 'error':
        $loginButton.removeClass('visually-hidden').prop('disabled', false).find('span').text('Try Again');
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
    return address.substring(0, 6) + '...' + address.substring(address.length - 4);
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
Drupal.behaviors.walletAuth.showMessage = function (message, type) {
  type = typeof type !== 'undefined' ? type : 'status';
  var $ = jQuery;
  var $messages = $('.messages__wrapper');
  if ($messages.length) {
    var $message = $('<div class="messages messages--' + type + '">' + message + '</div>');
    $messages.append($message);
    // Auto-remove after 5 seconds
    setTimeout(function () {
      $message.fadeOut();
    }, 5000);
  }
};
