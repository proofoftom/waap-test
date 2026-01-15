/**
 * @file
 * Wallet authentication UI integration with Drupal behaviors.
 */

import jQuery from "jQuery";
import Drupal from "Drupal";
import drupalSettings from "drupalSettings";

("use strict");

// Flag to ensure we only initialize once
var walletAuthInitialized = false;

/**
 * Wallet authentication behavior.
 *
 * Provides a single-click "Sign In" experience that handles the entire
 * authentication flow: connect wallet (if needed) -> sign message -> authenticate.
 */
Drupal.behaviors.walletAuth = {
  connector: null,
  state: "idle", // idle, signing, error, authenticated
  csrfToken: null, // Store CSRF token from nonce response
  buttonText: "Sign In", // Configurable button text
  displayMode: "link", // Display mode: 'link' or 'button'

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
    var waapConfig = config.waapConfig || {};

    // Add additional branding data to waapConfig
    waapConfig.projectName = config.projectName || '';
    waapConfig.projectLogo = config.projectLogo || '';
    waapConfig.projectEntryTitle = config.projectEntryTitle || '';
    waapConfig.walletConnectProjectId = config.walletConnectProjectId || '';

    // Initialize connector using namespaced class
    this.connector = new Drupal.walletAuth.WalletConnector(waapConfig);

    // Get configurable button text and display mode
    this.buttonText = config.buttonText || "Sign In";
    this.displayMode = config.displayMode || "link";

    // Apply display mode classes
    var $trigger = $(".wallet-auth-trigger", context);
    if (this.displayMode === "button") {
      // Add Drupal button classes for theme styling
      $trigger.addClass("button button--primary button--small");
    }

    // Bind sign-in trigger
    $trigger.on("click", function (e) {
      e.preventDefault();
      self.handleSignIn();
    });

    // Initialize connector silently (no UI state change)
    this.connector
      .init()
      .then(function () {
        // Ready state - button shows "Sign In"
        self.setState("idle");
        self.updateUI();
      })
      .catch(function (error) {
        console.error("Initialization error:", error);
        self.setState("error");
        self.showError("Failed to initialize wallet connection");
      });
  },

  /**
   * Handle sign-in button click - unified single-click flow.
   *
   * This method handles the entire authentication process:
   * 1. Check for existing wallet session (passive)
   * 2. If no session, show WaaP login modal
   * 3. Once wallet connected, proceed with signature flow
   * 4. Complete Drupal authentication
   */
  handleSignIn: function () {
    var self = this;

    this.setState("signing");
    this.updateUI();

    // Step 1: Check for existing wallet session (passive check)
    this.connector
      .checkSession()
      .then(function (existingAccount) {
        if (existingAccount) {
          // Wallet already connected, proceed to authentication
          console.log("Using existing wallet session:", existingAccount);
          return existingAccount;
        }

        // Step 2: No existing session - show WaaP login modal
        return self.connector.login().then(function (loginType) {
          if (!loginType) {
            // User cancelled the modal
            throw { cancelled: true };
          }
          console.log("Logged in via:", loginType);
          return self.connector.getAddress();
        });
      })
      .then(function (address) {
        // Step 3: Proceed with Drupal authentication
        return self.performAuthentication(address);
      })
      .catch(function (error) {
        if (error && error.cancelled) {
          // User cancelled - reset to idle state
          self.setState("idle");
          self.updateUI();
          return;
        }

        console.error("Sign-in error:", error);

        // Check if user rejected the signature request
        if (
          (error.message && error.message.includes("User rejected")) ||
          (error.message && error.message.includes("user rejected")) ||
          error.code === 4001
        ) {
          self.setState("idle");
          self.updateUI();
          self.showError("Signature request was cancelled. Please try again.");
        } else {
          self.setState("error");
          self.updateUI();
          self.showError(error.message || "Sign-in failed");
        }
      });
  },

  /**
   * Complete authentication flow: fetch nonce, sign, verify.
   *
   * @param {string} address
   *   The wallet address to authenticate.
   *
   * @return {Promise}
   *   Promise that resolves on successful authentication.
   */
  performAuthentication: function (address) {
    var self = this;

    // Step 1: Fetch nonce from backend
    return this.fetchNonce(address)
      .then(function (data) {
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
      })
      .then(function (authData) {
        // Step 4: Send signature and original message to backend for verification
        return self.sendAuthentication(
          address,
          authData.signature,
          authData.message,
          authData.nonce
        );
      })
      .then(function (response) {
        if (response.success) {
          // Authentication successful
          self.setState("authenticated");
          self.showSuccess("Signed in as " + response.username);
          // Redirect
          if (drupalSettings.walletAuth.redirectOnSuccess) {
            window.location.href = drupalSettings.walletAuth.redirectOnSuccess;
          } else {
            window.location.reload();
          }
        } else {
          throw new Error(response.error || "Authentication failed");
        }
      });
  },

  /**
   * Fetch nonce from backend.
   */
  fetchNonce: function (address) {
    var apiEndpoint = drupalSettings.walletAuth.apiEndpoint;

    return fetch(apiEndpoint + "/nonce?wallet_address=" + address, {
      method: "GET",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
    }).then(function (response) {
      if (!response.ok) {
        throw new Error("Failed to fetch nonce");
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

    var message =
      domain + " wants you to sign in with your Ethereum account:\n";
    message += address + "\n\n";
    message += "Sign in with Ethereum to prove ownership of your wallet.\n\n";
    message += "URI: " + uri + "\n";
    message += "Version: 1\n";
    message += "Chain ID: " + chainId + "\n";
    message += "Nonce: " + nonce + "\n";
    message += "Issued At: " + issuedAt + "\n";
    message += "Expiration Time: " + expirationTime;

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
    var apiEndpoint = drupalSettings.walletAuth.apiEndpoint + "/authenticate";

    return fetch(apiEndpoint, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
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
          throw new Error(data.error || "Authentication failed");
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
    jQuery("body").attr("data-wallet-auth-state", state);
  },

  /**
   * Update UI based on current state.
   *
   * States:
   * - idle: Ready to sign in, trigger shows configured text
   * - signing: Authentication in progress, trigger disabled
   * - authenticated: Success, redirecting
   * - error: Failed, trigger shows "Try Again"
   *
   * Supports both block template (has .wallet-auth-container and span) and
   * menu link (plain text content, no container).
   */
  updateUI: function () {
    var $ = jQuery;
    var $trigger = $(".wallet-auth-trigger");
    var $status = $(".wallet-auth-status");
    var $container = $(".wallet-auth-container");

    // Helper to set trigger text, handling both block (has span) and menu link (plain text).
    var setTriggerText = function ($el, text) {
      var $span = $el.find("span");
      if ($span.length) {
        $span.text(text);
      } else {
        $el.text(text);
      }
    };

    switch (this.state) {
      case "idle":
        $trigger.removeClass("is-disabled");
        setTriggerText($trigger, this.buttonText);
        if ($container.length) {
          $container.attr("data-wallet-auth-state", "idle");
        }
        if ($status.length) {
          $status.text("");
        }
        break;

      case "signing":
        $trigger.addClass("is-disabled");
        setTriggerText($trigger, "Signing in...");
        if ($container.length) {
          $container.attr("data-wallet-auth-state", "signing");
        }
        if ($status.length) {
          $status.text("Please complete the sign-in in your wallet...");
        }
        break;

      case "authenticated":
        $trigger.addClass("is-disabled");
        setTriggerText($trigger, "Signed In");
        if ($container.length) {
          $container.attr("data-wallet-auth-state", "authenticated");
        }
        if ($status.length) {
          $status.text("Authentication successful!");
        }
        break;

      case "error":
        $trigger.removeClass("is-disabled");
        setTriggerText($trigger, "Try Again");
        if ($container.length) {
          $container.attr("data-wallet-auth-state", "error");
        }
        if ($status.length) {
          $status.text("Sign-in failed. Please try again.");
        }
        break;
    }
  },

  /**
   * Show error message.
   */
  showError: function (message) {
    // Use Drupal messages or custom UI
    if (Drupal.behaviors.walletAuth.showMessage) {
      Drupal.behaviors.walletAuth.showMessage(message, "error");
    } else {
      alert(message);
    }
  },

  /**
   * Show success message.
   */
  showSuccess: function (message) {
    if (Drupal.behaviors.walletAuth.showMessage) {
      Drupal.behaviors.walletAuth.showMessage(message, "status");
    } else {
      console.log(message);
    }
  },

  /**
   * Format address for display.
   */
  formatAddress: function (address) {
    if (!address) return "";
    return (
      address.substring(0, 6) + "..." + address.substring(address.length - 4)
    );
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
  type = typeof type !== "undefined" ? type : "status";
  var $ = jQuery;
  var $messages = $(".messages__wrapper");
  if ($messages.length) {
    var $message = $(
      '<div class="messages messages--' + type + '">' + message + "</div>"
    );
    $messages.append($message);
    // Auto-remove after 5 seconds
    setTimeout(function () {
      $message.fadeOut();
    }, 5000);
  }
};
