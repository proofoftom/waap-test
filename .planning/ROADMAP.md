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

## Phase 4: Frontend Wallet Integration

**Goal:** Implement wallet connection and signing UI

**Status:** Ready to Start

**Deliverables:**
- NPM package build pipeline
- Wallet connection UI component
- Message signing integration
- Login button/block for Drupal

**Tasks:**
1. Set up NPM/build pipeline following safe_smart_accounts pattern
2. Install/configure Wallet as a Protocol SDK
3. Create wallet connection JavaScript
4. Implement message signing flow
5. Build Drupal block/plugin for login button
6. Attach JS library to Drupal pages
7. Handle wallet connection state

---

## Phase 5: Integration & Polish

**Goal:** Complete Drupal integration and refine UX

**Status:** Pending

**Deliverables:**
- Configurable module settings
- Proper error handling and user feedback
- Drupal coding standards compliance
- Basic admin configuration

**Tasks:**
1. Create module configuration form
2. Add admin settings (network configuration, etc.)
3. Implement error handling for failed auth
4. Add user-facing messages for auth states
5. Ensure Drupal coding standards compliance
6. Add basic documentation (README, inline comments)
7. Test complete authentication flow

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

**Progress:** 3/6 phases complete (50%)

**Estimated complexity:** Medium — Leverages existing patterns (safe_smart_accounts) and clear protocol spec

---

*Last updated: 2025-01-12*
