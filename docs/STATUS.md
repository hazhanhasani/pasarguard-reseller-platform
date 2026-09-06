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
- Provider operation, usage sync and reconciliation jobs. Partial Provider failures are isolated and retried without invalidating the central subscription.
- Subscription event timeline and audit-log schema foundations.

Quality coverage includes pure accounting/state regression tests plus MySQL feature tests for wallet idempotency, immutable ledger, zero-wallet/recharge behavior, historical rates, delta billing, counter regression, token rotation and Store/Reseller tenant boundary.

Still required before production release:
- Finish installer recovery/end-to-end installation flow and commit a reviewed `composer.lock`.
- Provider management UI, live version compatibility matrix and safe sandbox tools.
- Complete edit/reset/token operations and richer reconciliation/problem-center workflows.
- Reseller/Store CRUD, dashboards and authorization policies for all tenant resources.
- Single `/s/{token}` browser/JSON gateway, landing page and no-dedup JSON aggregation/cache.
- BluPal integration only after validating the current official documentation; webhook remains authoritative and idempotent.
- Reseller API, scoped API keys, rate limiting and OpenAPI documentation.
- Reports/exports, notifications/deduplication, System Health and diagnostic bundle.
- Backup/restore/update center, release ZIP validation/checksums/staging/rollback.
- Security hardening, 2FA option, complete audit writers, production load/fault tests and final cPanel deployment acceptance.

Cron policy: cPanel exclusively controls the schedule. No sync interval is hard-coded. Every `php artisan platform:tick` invocation performs one bounded cycle using configurable batch sizes, MySQL advisory locking, database queues and idempotent jobs.

Provider API contracts are based on the official PasarGuard/panel source reviewed 2026-09-06. No undocumented payment endpoint or payload is permitted in production code.
