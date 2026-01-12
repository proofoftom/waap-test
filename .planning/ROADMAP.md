# Roadmap: Wallet as a Protocol — Drupal Login Module

**Project:** Simple "Login with Wallet" for Drupal 10
**Mode:** yolo | **Depth:** standard
**Created:** 2025-01-12

---

## Overview

This roadmap breaks down the implementation of a Drupal 10 contrib module for wallet-based authentication using Wallet as a Protocol. The module enables users to authenticate by connecting their wallet and signing a message — no passwords required.

---

## Phase 1: Foundation & Environment Setup ✅

**Goal:** Establish working Drupal 10 development environment with project structure

**Status:** Complete (2025-01-12)

**Deliverables:**
- ✅ Fresh Drupal 10 project via composer (10.6.2)
- ✅ DDEV configuration for local development
- ✅ Module scaffold with basic Drupal structure
- ✅ Development environment validated

**Commits:** 13 (db04bdd through e1e9e1e)

**Artifacts:**
- `.ddev/config.yaml` - DDEV configuration
- `phpstan.neon`, `phpcs.xml` - Quality tools
- `web/modules/custom/wallet_auth/` - Module directory

---

## Phase 2: Wallet as a Protocol Integration Research ✅

**Goal:** Understand Wallet as a Protocol spec and SDK requirements

**Status:** Complete (2025-01-12)

**Deliverables:**
- ✅ Clear understanding of WaaP authentication flow
- ✅ Documented integration approach
- ✅ NPM package strategy following safe_smart_accounts pattern

**Artifacts:**
- `.planning/phases/02-protocol-integration/RESEARCH.md`
- Architecture Decision Records (ADR-003, ADR-004)

---

## Phase 3: Backend Authentication System ✅

**Goal:** Implement Drupal backend for wallet authentication

**Status:** Complete (2025-01-12)

**Deliverables:**
- ✅ Wallet verification service (EIP-191 signature verification)
- ✅ User creation/linking logic (via External Auth)
- ✅ Database schema for wallet-address mapping
- ✅ REST API endpoint (`/wallet-auth/authenticate`)

**Commits:** 8 (2e0e530 through 24de79a)

**Artifacts:**
- `web/modules/custom/wallet_auth/src/Service/WalletVerification.php`
- `web/modules/custom/wallet_auth/src/Service/WalletUserManager.php`
- `web/modules/custom/wallet_auth/src/Controller/AuthenticateController.php`
- `wallet_auth.install` - Database schema
- `.planning/phases/03-backend-auth/SUMMARY.md`

---

## Phase 4: Frontend Wallet Integration ✅

**Goal:** Implement wallet connection and signing UI

**Status:** Complete (2025-01-12)

**Deliverables:**
- ✅ NPM package build pipeline (Vite)
- ✅ Wallet connection UI component (WaaP SDK wrapper)
- ✅ Message signing integration (EIP-191 personal_sign)
- ✅ Login button/block for Drupal (WalletLoginBlock)

**Commits:** 13 (79a4fe2 through 44f95c5)

**Artifacts:**
- `web/modules/custom/wallet_auth/src/js/wallet-auth-connector.js` - WaaP SDK wrapper (269 lines)
- `web/modules/custom/wallet_auth/src/js/wallet-auth-ui.js` - Drupal behaviors (342 lines)
- `web/modules/custom/wallet_auth/src/css/wallet-auth.css` - Component styles
- `web/modules/custom/wallet_auth/src/Plugin/Block/WalletLoginBlock.php` - Block plugin
- `web/modules/custom/wallet_auth/templates/wallet-login-button.html.twig` - Twig template
- `web/modules/custom/wallet_auth/js/dist/` - Built JavaScript bundles
- `.planning/phases/04-frontend-integration/SUMMARY.md`

---

## Phase 5: Integration & Polish ✅

**Goal:** Complete Drupal integration and refine UX

**Status:** Complete (2025-01-12)

**Deliverables:**
- ✅ Configuration schema and defaults
- ✅ Admin settings form at `/admin/config/people/wallet-auth`
- ✅ Network, auto-connect, and nonce lifetime configuration
- ✅ Enhanced error handling with logging
- ✅ PHPCS and PHPStan both reporting 0 errors
- ✅ Comprehensive README with frontend instructions and troubleshooting

**Commits:** 11 (548d7afd through 118fbc05)

**Artifacts:**
- `config/schema/wallet_auth.schema.yml` - Configuration schema
- `config/install/wallet_auth.settings.yml` - Default config
- `src/Form/SettingsForm.php` - Admin settings form
- `wallet_auth.links.menu.yml` - Admin menu link
- `README.md` - Updated with comprehensive documentation
- `.planning/phases/05-integration-polish/SUMMARY.md`

**Tasks Completed:**
1. ✅ Create configuration schema
2. ✅ Create default configuration
3. ✅ Create settings form
4. ✅ Register admin route
5. ✅ Add link to admin menu
6. ✅ Update NonceController to use config
7. ✅ Update frontend to read config
8. ✅ Review error handling in backend
9. ✅ Run PHPCS and fix issues (0 errors)
10. ✅ Run PHPStan and fix issues (0 errors)
11. ✅ Update README with frontend instructions
12. ✅ Clear caches and verify module

---

## Phase 6: Testing & Validation

**Goal:** Ensure production-ready quality

**Status:** Pending

**Deliverables:**
- Working authentication flow end-to-end
- Tested on fresh Drupal install
- Security review completed
- Ready for contrib release

**Tasks:**
1. Test complete flow: connect → sign → login
2. Test account creation on first auth
3. Test existing user login
4. Security review (signature verification, XSS, etc.)
5. Code quality review
6. Documentation finalization
7. Prepare for Drupal.org contrib release

---

## Summary

**6 Phases** spanning from environment setup through production-ready release

**Critical path:** Phase 1 → 2 → 3 → 4 → 5 → 6

**Progress:** 5/6 phases complete (83%)

**Estimated complexity:** Medium — Leverages existing patterns (safe_smart_accounts) and clear protocol spec

---

*Last updated: 2025-01-12*
