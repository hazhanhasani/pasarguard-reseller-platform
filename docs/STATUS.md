# Development status

This is not a deployable production release. Do not sell subscriptions using this branch yet.

Implemented: Laravel foundation, role-protected login, initial MySQL migrations, integer billing calculation, desired-state calculation, bounded queue tick with MySQL lock, guarded initial web installer, documented PasarGuard HTTP adapter, encrypted provider credential model.

Verified on GitHub Actions: initial core and auth/MySQL suites; installer dotenv security suite. Provider contract tests use mocked HTTP; they do not certify any live provider.

Still missing: complete installer recovery/end-to-end TLS tests; provider management/probes; master subscriptions/mappings; usage sync; transactional ledger services; reseller/store CRUD; gateway and landing page; BluPal integration; dashboards/reports/notifications; import/API; backup/restore/update; complete security and production acceptance.

No ready-to-install release ZIP is published. Composer lock is currently an Actions artifact and must be reviewed and committed before reproducible release packaging.

Provider API contracts were read from official PasarGuard/panel source, 2026-09-06:
- app/routers/user.py: user CRUD, boolean disabled, usage reset, pagination, administrative subscription xray output.
- app/routers/authentication.py: X-Api-Key authentication.
- app/models/user.py: group_ids, data_limit, expire, used_traffic and lifetime_used_traffic.
- app/models/settings.py: xray output enum.

Compatibility must be checked against each provider's actual version. The adapter currently uses API keys, not username/password token acquisition. IPv4-resolvable public HTTPS origins only; redirects are disabled and DNS results pinned. HTTP timeout values are network budgets, not sync schedules.
