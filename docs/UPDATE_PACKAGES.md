# Update package format

Production updates are uploaded from **Super Admin → Update Center** as a ZIP file. Packages are validated before they can be applied.

## Layout

```text
release.zip
├── manifest.json
├── application/
│   └── ... files relative to the project root ...
├── migrations/
│   └── 2026_09_07_000001_example.php
└── changelog.md
```

`application/` paths are copied relative to the project root. `migrations/` files are copied only into `database/migrations/` and must use a new filename.

## Manifest

```json
{
  "version": "0.11.0",
  "minimum_version": "0.10.0",
  "php_requirement": ">=8.3",
  "database_requirement": "mysql>=8.0|mariadb>=10.3",
  "migration_version": 7,
  "release_date": "2026-09-06T16:00:00Z",
  "release_channel": "stable",
  "checksums": {
    "application/app/Example.php": "<sha256>",
    "migrations/2026_09_07_000001_example.php": "<sha256>",
    "changelog.md": "<sha256>"
  }
}
```

Every file except `manifest.json` must have an exact SHA-256 entry. Extra checksum entries are rejected.

## Protected data

An update package cannot overwrite `.env`, `storage/`, `public/uploads/`, `bootstrap/cache/`, `.git/`, or the existing `database/migrations/` tree through `application/`. New migrations must use the dedicated `migrations/` package directory.

Update ZIPs, backup ZIPs, runtime locks, credentials, uploaded logos, and other persistent data are stored outside the updateable application paths.

## Migration policy

Update migrations are **safe-forward only**. Packages are rejected when migration source contains destructive operations such as dropping tables/columns, truncation, delete-all SQL, or renaming/removing columns. Existing migration filenames cannot be replaced.

## Apply sequence

1. ZIP safety validation
2. Manifest validation
3. SHA-256 verification
4. PHP/database/version compatibility check
5. Maintenance write lock
6. Automatic full safety backup
7. Stream extraction to staging
8. Snapshot files that will change
9. Atomic file replacement
10. Run migrations
11. Clear Laravel caches
12. Health check
13. Persist the new application version

If file application or health validation fails, changed application files are restored where possible. Once migrations have started, migration files are preserved and database changes are treated as safe-forward. The automatic safety backup remains available for an operator-controlled restore.
