# Development status

This is not yet a deployable production release. Do not sell subscriptions using this branch until all production phases and acceptance checks are complete.

Implemented foundations:
- Laravel/cPanel foundation, role-protected login, guarded HTTPS installer and MySQL-backed bounded `platform:tick` lock.
- Provider adapter abstraction and documented PasarGuard HTTP adapter with encrypted provider credentials and non-destructive connection/capability probing.
- Master Subscription schema using independent ULIDs, cryptographically random central tokens, encrypted token storage, immediate rotate/revoke lookup invalidation, Provider mappings and idempotent Provider operation records.
- Desired-state persistence for `active`, `manual_suspended`, `wallet_zero`, `quota_exceeded`, `expired`, `deleted`.
- Delta-based usage snapshots; counter regression becomes `reconcile_required` and is never interpreted as zero usage.
- Global usage accounting in bytes, global price history, integer minor-unit billing with fractional carry, immutable Wallet Ledger and row-locked/idempotent wallet mutations.
- Zero-wallet transition: only active subscriptions become `wallet_zero`; recharge only reevaluates subscriptions whose state is `wallet_zero`.
- Provider operation, usage sync, cached subscription-output refresh and reconciliation jobs. Partial Provider failures keep the last valid customer output and are isolated from healthy Providers.
- Reseller/Store dashboards and CRUD, server-side subscription pagination/filtering and tenant-scoped mutation paths.
- Single `/s/{token}` gateway: normal browsers receive a light mobile-first landing page, recognized subscription clients receive merged JSON on the same URL, and ambiguous requests fall back to HTML. JSON keeps duplicates and broken entries and does not expose Provider metadata.
- BluPal invoice creation and status verification based only on current official documented endpoints. API keys are encrypted in DB, payment creation redirects directly to the official payment link, webhook payloads are never trusted for wallet credit, and server-to-server invoice verification plus immutable ledger references enforce idempotency. Pending-payment verification is recoverable through `platform:tick`.
- Subscription event timeline and audit-log schema foundations.

Quality coverage includes pure accounting/state regression tests plus MySQL feature tests for wallet idempotency, immutable ledger, zero-wallet/recharge behavior, historical rates, delta billing, counter regression, token rotation, Store/Reseller tenant boundary, browser/client subscription gateway behavior, duplicate-preserving aggregation and payment idempotency.

Still required before production release:
- Finish installer recovery/end-to-end installation flow and commit a reviewed `composer.lock`.
- Provider management UI, live version compatibility matrix and safe sandbox tools.
- Complete edit/reset/token operations and richer reconciliation/problem-center workflows.
- Reseller API, scoped API keys, rate limiting and OpenAPI documentation.
- Expand BluPal operational tooling (sandbox diagnostics, payment-error center and notification hooks); no undocumented callback parameter or webhook signature may be invented.
- Reports/exports, notifications/deduplication, System Health and diagnostic bundle.
- Backup/restore/update center, release ZIP validation/checksums/staging/rollback.
- Security hardening, 2FA option, complete audit writers, production load/fault tests and final cPanel deployment acceptance.

Cron policy: cPanel exclusively controls the schedule. No sync interval is hard-coded. Every `php artisan platform:tick` invocation performs one bounded cycle using configurable batch sizes, MySQL advisory locking, database queues and idempotent jobs.

Provider API contracts are based on the official PasarGuard/panel source reviewed 2026-09-06. BluPal payment contracts were checked against the official documentation on 2026-09-06. No undocumented payment endpoint, payload, callback field or signature scheme is permitted in production code.
