# Architecture decisions and release gates

## Scope and state
Target: PHP 8.3+ 64-bit, Laravel 13 candidate, MySQL/InnoDB, Blade RTL, database queue, bounded CLI ticks. Framework dependencies have not been installed or locked. No production implementation is claimed. No provider requests were made.

## Aggregate boundaries
Reseller owns wallet and stores. Subscription has independent random master ID and belongs to exactly one reseller/store pair. Enforce composite foreign keys, not only controller filters. Store and provider deletion use archive timestamps; mapping teardown remains queued. Never cascade deletion into ledger, usage or payment history.

Tables planned: users, resellers, stores, providers, subscriptions, provider_mappings, usage_observations, usage_charges, wallets, wallet_ledger, global_prices, payments, api_keys, jobs, outbox_events, audit_logs, notifications, update_runs, backup_runs, settings, schema_migrations. Financial rows are append-only; corrections use compensating entries.

## Transaction protocol
Lock wallet first, then subscription, then mapping in stable ID order. A successful usage observation updates snapshot, inserts unique observation, calculates charge using an immutable rate ID, appends ledger, updates wallet projection and desired state, and writes outbox records in one database transaction. Do not hold database locks during HTTP calls. Retry deadlocks with bounded attempts.

Webhook processing first identifies an existing local payment and verifies with authenticated gateway status retrieval. Inside transaction lock payment and wallet; compare gateway invoice, amount, environment and status; unique ledger reference payment:<local-id> prevents duplicates. Unknown invoice cannot create wallet credit. Repeated events return success without modifying balance. Browser callback renders local persisted status only.

## Precision and price history
GB = 1,000,000,000 bytes. Currency = IRR. Charge whole minor units and persist integer fractional numerator on the wallet. Keep each charge's rate, bytes and remainder before/after for audit; never reprice old observations. Revenue and deposits are separate report categories.

A cumulative provider counter does not reveal when bytes were consumed between observations. Exact tariff attribution across a price change requires timestamped provider usage, or a documented observed-at pricing rule. Do not pretend it is knowable. Likewise a reset can discard unobserved consumption. Preserve previous snapshot and flag uncertain data; require verified epoch and baseline. Migration import seeds preexisting usage as baseline and does not retroactively bill it.

## Quota enforcement limit
A central subscription gateway controls retrieval, not traffic on already imported configurations. Periodic provider polling necessarily allows detection delay; offline APIs may prevent immediate disable. Strict global hard caps require provider-side allocated budgets whose sum stays within available quota, or real-time shared enforcement. Budget allocation must account for wallet shared across every subscription and provider. This remains an unresolved production acceptance issue, not a promised guarantee.

## Sync
CLI tick processes bounded batches with configurable batch/time budget; schedule exists solely in cPanel cron. Use MySQL GET_LOCK on a dedicated connection for the whole tick and fail closed on lock/connection loss. Individual queue jobs have leases and unique business keys. Tick must not rely on a long-running daemon.

Deterministic provider username derived from master ID plus provider mapping supports retry after ambiguous create timeout; read before retry, verify identity, never take ownership of unrelated existing users. Desired revision travels with jobs; stale jobs cannot reverse newer manual actions. Failures stay isolated per mapping.

Maintenance stops new allocations; existing accounting and safety disable obligations continue. Disabled provider snapshots remain in historical totals. Mark failed reads stale, not zero. Fair pagination prevents one failing provider from starving the remainder.

## Subscription output
Exactly /s/<token>. Random 32-byte token, hash lookup, optionally encrypted recoverable token if link retrieval is required. Rotation commits new token and revokes old cache keys atomically. Check desired state and wallet at every request, including cache hits. Browser fetch metadata and explicit HTML preference win; known client signature plus negotiated JSON selects JSON; ambiguous requests receive HTML.

Keep cached provider outputs encrypted server-side. Concatenate arrays preserving order and duplicates. A syntactically valid but broken config stays. An invalid JSON document cannot be embedded as JSON configs; preserve prior valid document and show admin-only parse error. Do not invent conversion. Transport/domain/IP information required for connectivity cannot be hidden from technical inspection; remove branding metadata without altering functional connection fields. Never expose provider administrative credentials or subscription tokens.

## Installer and updates
Installer must require a one-time locally provisioned setup secret to prevent first-visitor takeover. Verify extensions, TLS, database, storage permissions, and public document root; generate key atomically; migrate forward; create admin; lock installer persistently. Never expose .env or source under public document root.

Update ZIP checks include size/count/compression ratio, path traversal, absolute paths, symlinks, duplicate normalized names, protected paths, checksums and version constraints. ZIP is executable code: checksums alone do not establish publisher trust. Use a trusted signing key or explicit trusted-package policy. Stage before maintenance; backup database and persistent files; journal each operation; atomic file switches where supported. Roll files back only to a version compatible with the forward-migrated schema. No automatic destructive down migrations.

Backups must be outside document root, encrypted with separately recoverable key material, downloadable only through authorized streaming. Restore validates manifest/version, makes safety backup, and runs under exclusive lock. Retention never removes the current safety copy or only usable recovery point.

## Integration research, 2026-09-06
Official sources consulted:
- https://laravel.com/docs/13.x/deployment — PHP 8.3 minimum.
- https://docs.pasarguard.org/en/ — official documentation located; exact panel API contract still requires review before implementation.
- https://github.com/PasarGuard/panel — official repository located.
- https://blupal.net/documentation — server-side X-API-Key authentication, invoice create and status API documented; sandbox exists.

BluPal full webhook signature and callback contracts have not yet been reviewed. No invented callback parameter or signature header is implemented. Payment adapter must stay disabled until contracts are confirmed and sandbox tests pass.

## Phase gates
1. Core/auth/installer: pending; actual MySQL migration and authorization tests required.
2. Provider contracts: pending; version-pinned fixture and sandbox CRUD suite.
3. Mapping: pending; ambiguous timeout and duplicate-create tests.
4. Usage/sync: pending; reset, stale snapshots and fair retry tests.
5. Billing: pure calculation draft only; real concurrent MySQL ledger tests pending.
6. Reseller/store: pending; cross-tenant negative tests mandatory.
7. Gateway: pending; rotation, negotiation, duplicate preservation tests.
8. Payment: pending; sandbox duplicate webhook and mismatch tests.
9. UI/reports/notifications: pending; actual RTL mobile/browser checks.
10. Backup/update: pending; traversal, tampered archive and crash-recovery tests.
11. Hardening: pending; session revocation, API scopes, SSRF and secret-redaction tests.
12. Deployment: pending; fresh cPanel install, cron overlap, upgrade/restore and live provider acceptance.

No phase has passed. PHP tests in this archive have not been executed.
