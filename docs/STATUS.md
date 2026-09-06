# Development status

This is not yet a deployable production release. Do not sell subscriptions using this branch until all production phases and acceptance checks are complete.

Implemented foundations:
- Laravel/cPanel foundation, role-protected login, guarded HTTPS installer and MySQL-backed bounded `platform:tick` lock.
- Provider adapter abstraction and documented PasarGuard HTTP adapter with encrypted provider credentials.
- Provider Center: encrypted add/edit, automatic non-destructive Test Connection, READY gate before Active mode, Maintenance/Disabled modes, capability matrix, latency/error-aware health score and Force Sync enqueueing.
- Problem Center: paginated pending/retrying/failed Provider operations, bounded Retry All, per-operation Retry and Force Reconciliation.
- Deduplicated Super Admin notification foundation with occurrence counters, read/resolve state, Provider health and queue-backlog signals.
- Master Subscription schema using independent ULIDs, cryptographically random central tokens, encrypted token storage, immediate rotate/revoke invalidation, Provider mappings and idempotent Provider operations.
- Desired-state persistence for `active`, `manual_suspended`, `wallet_zero`, `quota_exceeded`, `expired`, `deleted`.
- Delta-based usage snapshots; counter regression becomes `reconcile_required` and is never interpreted as zero usage.
- Global usage accounting in bytes, global price history, integer minor-unit billing with fractional carry, immutable Wallet Ledger and row-locked/idempotent wallet mutations.
- Zero-wallet transition: only active subscriptions become `wallet_zero`; recharge only reevaluates subscriptions whose state is `wallet_zero`.
- Provider operation, usage sync, cached subscription-output refresh and reconciliation jobs. Partial Provider failures keep the last valid customer output and are isolated from healthy Providers.
- Reseller/Store dashboards and CRUD, server-side subscription pagination/filtering and tenant-scoped mutation paths.
- Single `/s/{token}` gateway: browser HTML and subscription-client JSON on the same URL; ambiguous requests fall back to HTML. JSON preserves duplicates and broken entries and never includes Provider metadata.
- BluPal invoice creation and status verification based only on official documented endpoints. API keys are encrypted in DB, payment creation redirects directly to BluPal, webhook payloads are not trusted for wallet credit, and server-to-server invoice verification plus immutable ledger references enforce idempotency. Pending verification recovers through `platform:tick`.
- Subscription event timeline and audit-log schema foundations.

Quality coverage includes accounting/state regression plus MySQL tests for wallet idempotency, zero-wallet/recharge, historical rates, delta billing, token rotation, tenant isolation, browser/client gateway behavior, duplicate-preserving aggregation, payment idempotency, non-destructive Provider probing, READY activation guard and notification deduplication.

Still required before production release:
- Finish installer recovery/end-to-end installation flow and commit a reviewed `composer.lock`.
- Expand Provider live compatibility/version diagnostics and safe sandbox write tests without production-user mutation.
- Complete edit/reset/token operations and richer mapping-level inspection tools.
- Reseller API, scoped API keys, rate limiting and OpenAPI documentation.
- Reports/exports and reseller notification center; expand Admin notifications for payment/backup/update/disk/cron conditions.
- System Health and diagnostic bundle.
- Backup/restore/update center, release ZIP validation/checksums/staging/rollback.
- Security hardening, optional 2FA/session revocation, complete audit writers, production load/fault tests and final cPanel deployment acceptance.

Cron policy: cPanel exclusively controls the schedule. No sync interval is hard-coded. Every `php artisan platform:tick` invocation performs one bounded cycle using configurable batch sizes, MySQL advisory locking, database queues and idempotent jobs. Health freshness values are thresholds only and do not schedule work.

Provider API contracts are based on the official PasarGuard/panel source reviewed 2026-09-06. BluPal payment contracts were checked against the official documentation on 2026-09-06. No undocumented payment endpoint, payload, callback field or signature scheme is permitted in production code.
