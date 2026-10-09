---
guide: bc-data-and-config
title: Will existing shops, API clients and operators survive this data or config change?
personas: [architecture, maintainer]
rules:
  - { id: BCDATA-001, since: 2026-10-09, source: "coding-guidelines/core/backward-compatibility.md#database", origin: "https://github.com/shopware/shopware/pull/20934", fixture: "tests/guide/bc-data-and-config/catch" }
  - { id: BCDATA-002, since: 2026-10-09, source: "coding-guidelines/core/backward-compatibility.md#store-and-admin-api", origin: "https://github.com/shopware/shopware/pull/11033", fixture: "" }
  - { id: BCDATA-003, since: 2026-10-09, source: "adr/2023-02-02-deprecate-autoload-true-in-dal-associations.md", origin: "adr/2023-02-02-deprecate-autoload-true-in-dal-associations.md", fixture: "" }
  - { id: BCDATA-007, since: 2026-10-09, source: "coding-guidelines/core/backward-compatibility.md#flags-and-experimental", origin: "UPGRADE-6.8.md (DOCUMENT_GENERATION_REWORK becomes opt-out)", fixture: "" }
  - { id: BCDATA-008, since: 2026-10-09, source: "coding-guidelines/core/backward-compatibility.md#configuration-and-operations", origin: "https://github.com/shopware/shopware/pull/14762", fixture: "" }
  - { id: BCDATA-009, since: 2026-10-09, source: "coding-guidelines/core/backward-compatibility.md#configuration-and-operations", origin: "https://github.com/shopware/shopware/pull/16721", fixture: "" }
  - { id: BCDATA-010, since: 2026-10-09, source: "coding-guidelines/core/backward-compatibility.md#configuration-and-operations", origin: "https://github.com/shopware/shopware/pull/15875", fixture: "" }
---

## Why this guide exists

Schema, DAL definitions, feature flag defaults and configuration defaults reach every shop on update, and most of them cannot be rolled back. The 6.8 stability direction counts changed defaults and data behaviour as breaks, even when no PHP signature changes. On a `6.x` base branch the previous release must still run on the new schema (blue-green) and every default stays; on `trunk` such changes go behind the next major flag or into `updateDestructive()` of the next major. This guide covers the DAL definition, the data shape the APIs expose and config contracts; migration mechanics (blue-green safe steps, flag-gated migrations, privilege grants) live in the migrations guide.

## Check

- **BCDATA-001** An existing optional field becomes required: `NOT NULL` or a new constraint in a migration, `new Required()` or a stricter constraint on the DAL field, a required Store API parameter. API clients that never sent the field now get validation errors, and the previous release fails to insert rows on the new schema. DB table, DAL definition and Store API must change together, with a plan: nullable now, validation behind the major flag, `NOT NULL` in the next major. Tightened validation needs the same care: data stored under the old rule must still work downstream, for example in order creation (#17188). Rule: [Database](../../../../coding-guidelines/core/backward-compatibility.md#database). Example: #20934, a required product review rating.
- **BCDATA-002** A field or association gains or loses the `ApiAware` flag, or is removed or renamed in a definition. The Store or Admin API response changes for every client and generated SDK. Rule: [Store and Admin API](../../../../coding-guidelines/core/backward-compatibility.md#store-and-admin-api). Example: #11033 changed the `ApiAware` flag of the product review `externalUser` field, which changed the Store API schema.
- **BCDATA-003** An association is removed, or `autoload` is switched off, on an existing definition. Templates, API clients and plugins that relied on the loaded data get `null` without any error. Rule: [ADR autoload](../../../../adr/2023-02-02-deprecate-autoload-true-in-dal-associations.md). Example: the 2023 ADR that deprecates `autoload: true`.
- **BCDATA-007** A feature flag default flips, or a flag is renamed or removed. Shops change behaviour on update without touching anything. A flipped default needs a RELEASE_INFO entry and an UPGRADE entry that explains the opt-out. Rule: [Flags and experimental](../../../../coding-guidelines/core/backward-compatibility.md#flags-and-experimental). Example: document generation v2 became opt-out in 6.8 ([UPGRADE-6.8.md](../../../../UPGRADE-6.8.md)).
- **BCDATA-008** A default, key or environment variable in `config.xml`, `shopware.yaml` or system config changes or disappears. Operators who never set the value get new behaviour, and deployments that set the old key silently lose it. Rule: [Configuration and operations](../../../../coding-guidelines/core/backward-compatibility.md#configuration-and-operations). Example: #14762 changed the `allowed_types` default.
- **BCDATA-009** A CLI command changes for existing callers: an option is renamed or removed, an existing invocation now needs a new option, the exit code or the machine-readable output changes. Deployment scripts, cron jobs and health checks call commands with fixed arguments and branch on exit codes, so they fail after the update. Keep old invocations working and document the change. Rule: [Configuration and operations](../../../../coding-guidelines/core/backward-compatibility.md#configuration-and-operations). Example: #16721 (`app:list` evaluating the new `format` option first), #17314 (`dal:validate` now failing existing health checks).
- **BCDATA-010** A new configuration key that operators cannot find: declared in a bundle `Configuration.php` or only in the test configuration, but not with its default in the shipped package YAML and in `config-schema.json`. Operators do not know the option exists or what it does by default. Rule: [Configuration and operations](../../../../coding-guidelines/core/backward-compatibility.md#configuration-and-operations). Example: #15875 (Elasticsearch option missing from `elasticsearch.yaml`), #17481 (option only in the test configuration), #18746 (`Configuration.php` without `config-schema.json`).

## Do not flag

### CI covers

- `NoDropStatementInUpdateRule`, `AddColumnRule`, `NoAfterStatementRule`, `NonStandardFkGuardRule`: migration mechanics, listed in the migrations guide.
- `NoDALAutoload`: new associations with `autoload: true`. `FeatureFlagVersionRule`: malformed flag ids.
- Danger `ShopwareYamlConfigSchemaHint`: `shopware.yaml` changed without `config-schema.json`. The OpenAPI snapshot bot posts the API schema diff.

### Legitimate patterns

- New tables, nullable columns, columns with defaults, indexes, new fields and associations.
- On `trunk`: `NOT NULL` or a removal in `updateDestructive()` of the next major namespace, or validation behind `Feature::isActive('v6.8.0.0')`.
- New config keys and flags whose default keeps today's behaviour; test code.
- Migration mechanics (destructive steps in `update()`, flag-gated migrations, privilege grants, idempotency, timestamps, duration): the migrations guide owns them (MIG-001 to MIG-010). An optional field made required stays BCDATA-001, also in the migration.

## Severity

- `blocking`: an optional field made required in a minor. API clients get errors after a routine update, and the previous release fails to write on the new schema.
- `major`, `requires_human: true`: a changed default, a flipped flag, or an `ApiAware` or association change without release docs. Behaviour changes silently for every shop.
- `major`: a CLI invocation that worked before fails or behaves differently (BCDATA-009).
- `minor`: a changed default or flipped flag documented in only one of RELEASE_INFO and UPGRADE, or a new config key operators cannot find (BCDATA-010).

## Retired

- BCDATA-004 (2026-10-09): duplicate of MIG-002 in migrations.md.
- BCDATA-005 (2026-10-09): duplicate of MIG-008 in migrations.md.
- BCDATA-006 (2026-10-09): duplicate of MIG-007 in migrations.md.
