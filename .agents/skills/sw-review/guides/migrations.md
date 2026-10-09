---
guide: migrations
title: Will this migration run safely on every shop, and run again after a failure?
personas: [architecture]
rules:
  - { id: MIG-001, since: 2026-10-09, source: "coding-guidelines/core/database-migations.md#2-migrations-must-be-able-to-be-executed-more-than-once", origin: "https://github.com/shopware/shopware/pull/15865", fixture: "tests/guide/migrations/catch" }
  - { id: MIG-002, since: 2026-10-09, source: "coding-guidelines/core/database-migations.md#the-migration-class", origin: "https://github.com/shopware/shopware/pull/16282", fixture: "tests/guide/migrations/catch" }
  - { id: MIG-003, since: 2026-10-09, source: "coding-guidelines/core/database-migations.md#1-never-change-an-executed-migration", origin: "https://github.com/shopware/shopware/pull/15990", fixture: "" }
  - { id: MIG-004, since: 2026-10-09, source: "coding-guidelines/core/database-migations.md#6-performance--duration", origin: "https://github.com/shopware/shopware/pull/15990", fixture: "" }
  - { id: MIG-005, since: 2026-10-09, source: "coding-guidelines/core/database-migations.md#6-performance--duration", origin: "https://github.com/shopware/shopware/pull/16364", fixture: "" }
  - { id: MIG-006, since: 2026-10-09, source: "coding-guidelines/core/database-migations.md#5-dont-hurt-customized-data", origin: "https://github.com/shopware/shopware/pull/19397", fixture: "" }
  - { id: MIG-007, since: 2026-10-09, source: ".agents/skills/shopware-admin-js/SKILL.md#code", origin: "https://github.com/shopware/shopware/pull/20845", fixture: "" }
  - { id: MIG-008, since: 2026-10-09, source: "coding-guidelines/core/feature-flags.md#major-ci", origin: "https://github.com/shopware/shopware/pull/19836", fixture: "" }
  - { id: MIG-009, since: 2026-10-09, source: ".agents/skills/shopware-php-code/SKILL.md#migrations", origin: "https://github.com/shopware/shopware/pull/17247", fixture: "" }
  - { id: MIG-010, since: 2026-10-09, source: "coding-guidelines/core/database-migations.md#8-migration-tests", origin: "https://github.com/shopware/shopware/pull/15892", fixture: "tests/guide/migrations/ignore" }
---

## Why this guide exists

A migration runs once, unattended, on thousands of shops with data and timeouts we never see. When it fails halfway, the operator re-runs the update; when it breaks the schema the old release still reads, the shop fails during a blue-green deployment. Review comments on migrations repeat the same few points (about 40 comments between April and October 2026), so this guide lists them once.

## Check

- **MIG-001** Look for `ALTER TABLE`, `CREATE INDEX`, `INSERT` or `CREATE TABLE` without an existence check (`TableHelper::columnExists`/`indexExists`, `addColumn()`, `IF NOT EXISTS`, insert only missing rows). A migration that timed out halfway then fails on every re-run, and the update is stuck until someone fixes the database by hand. Rule: [Migrations must be able to be executed more than once](../../../../coding-guidelines/core/database-migations.md#2-migrations-must-be-able-to-be-executed-more-than-once). Example: #15865, #17483, #19185.
- **MIG-002** Look for a drop, rename, type narrowing, `NOT NULL` without a default or a dropped default in `update()`, also inside an `if` block or built with `sprintf`. During a blue-green deployment the old release still reads and writes the column and fails with SQL errors. Move it to `updateDestructive()` of the current major's migration. Rule: [The migration class](../../../../coding-guidelines/core/database-migations.md#the-migration-class), [Backward compatibility](../../../../coding-guidelines/core/database-migations.md#backward-compatibility). Example: #16282, #17483.
- **MIG-003** Look for a diff to an existing migration file. Shops that already ran it never get the change, so their schema differs from a fresh install. Whether the migration was part of a public release decides: released means a new migration, unreleased (for example a next-major migration) may still be edited. Rule: [Never change an executed migration](../../../../coding-guidelines/core/database-migations.md#1-never-change-an-executed-migration). Example: #15990 (released 6.6 migration edited), #16344 (unreleased 6.8 migration, editing was fine).
- **MIG-004** Look for derived data (hashes, keywords, listing values) recomputed in SQL across a whole table. Large shops hit the update timeout. Register the responsible indexer with `registerIndexer()`, or use a post-update indexer for a one-time backfill. Rule: [Performance / Duration](../../../../coding-guidelines/core/database-migations.md#6-performance--duration); the indexer advice itself: guideline paragraph pending. Example: #15990, #17672.
- **MIG-005** Look for data migrations that load or update a large table in one statement or without a deterministic `ORDER BY` between batches. The 10-second budget is exceeded, and unordered batches skip or repeat rows. Process about 1,000 rows per batch and keep a cursor. Rule: [Performance / Duration](../../../../coding-guidelines/core/database-migations.md#6-performance--duration); batching: guideline paragraph pending. Example: #16364, #19261.
- **MIG-006** Look for updates to mail templates, flows, CMS pages or config values without an `updated_at IS NULL` style guard. Merchants lose their own edits without notice. Rule: [Don't hurt customized data](../../../../coding-guidelines/core/database-migations.md#5-dont-hurt-customized-data). Example: #19397 (an existing merchant flow got a mail action), #17127.
- **MIG-007** Look for new ACL privileges without a migration for existing roles, or a privilege migration that writes `acl_role` directly instead of `addAdditionalPrivileges()`. Existing users lose access, or roles a merchant restricted gain rights. Rule: [Admin JS Code](../../shopware-admin-js/SKILL.md#code), [Don't hurt customized data](../../../../coding-guidelines/core/database-migations.md#5-dont-hurt-customized-data). Example: #20845, #16690.
- **MIG-008** Look for a migration gated by `Feature::isActive()`, or placed in the next major's namespace although it must run on the current line. Migrations are selected by the installed version, never by a flag, so the change runs too early, too late or never, and cannot be undone when the flag is switched off. Migrate only data and schema; keep the behaviour behind the major flag. Rule: [Major CI](../../../../coding-guidelines/core/feature-flags.md#major-ci). Example: #19836 (a `V6_8` migration that 6.7 shops never ran), #20863.
- **MIG-009** Look at the timestamp in the class name and `getCreationTimestamp()`. A placeholder or rounded value (`1780000000`) sorts the migration before or after the wrong steps. Use the exact creation time. Rule: [PHP code, Migrations](../../shopware-php-code/SKILL.md#migrations). Example: #17247.
- **MIG-010** Look at the migration test: it should call `update()` twice to prove MIG-001, and it should not exist for an empty `updateDestructive()`. Rule: [Migration Tests](../../../../coding-guidelines/core/database-migations.md#8-migration-tests). Example: #15892.

## Do not flag

### CI covers

- PHPStan `NoDropStatementInUpdateRule`: `DROP TABLE`, `DROP COLUMN`, `DROP FOREIGN KEY` literals and `drop*IfExists()` calls written as top-level statements of `update()`. It misses drops inside `if` blocks or loops, SQL built with `sprintf`, and renames (`CHANGE`, `RENAME COLUMN`); report those under MIG-002.
- PHPStan `AddColumnRule` (raw `ADD COLUMN` instead of `addColumn()`), `NoAfterStatementRule` (`ALTER TABLE ... AFTER`), `NonStandardFkGuardRule` (raw DDL on tables with known foreign key drift outside `executeDdlStatement()`).
- Danger `MissingMigrationTests`: a new migration file without a test under `tests/migration/`.

### Legitimate patterns

- Edits to a migration of the next major that has not been released (MIG-003).
- An empty `updateDestructive()`, and drops in `updateDestructive()`.
- Native time reads in migrations; PHPStan exempts migrations from the clock rules on purpose.
- An optional field made required across DB, DAL definition and Store API, `ApiAware` or association changes, and config or flag defaults: the bc-data-and-config guide owns the DAL definition, API-visible data shape and config contracts.

## Severity

- `blocking`: a destructive step in `update()` of a migration that ships in a minor, or an edited released migration. Shops fail during deployment, or their schema silently differs from a fresh install.
- `major`: a migration that cannot run twice, overwrites merchant data, recomputes a whole table, or is gated by a flag. The update gets stuck or merchants lose data.
- `minor`: a placeholder timestamp, or a test that runs `update()` only once.

## Retired

(none yet)
