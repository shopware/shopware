# Shopware 6

Shopware is an open-source e-commerce platform with API-first architecture exposing three distinct APIs (Admin, Store, Sync) alongside a built-in Twig-based storefront. It uses a custom Data Abstraction Layer instead of a traditional ORM, an event-driven extension system replacing decorators, and Flow Builder for business automation.

## Project Structure

```
shopware/
├── src/
│   ├── Core/                     # Business logic & framework
│   ├── Administration/           # Admin UI
│   ├── Storefront/               # Frontend
│   └── Elasticsearch/            # Search integration
├── tests/                        # Test suites
└── bin/console                   # CLI commands
```

## Technology Stack

- **Backend**: PHP 8.2+, Symfony 7, Doctrine DBAL 4
- **Frontend Admin**: Vue 3, Pinia + Vuex, Vite, TypeScript
- **Frontend Storefront**: Twig, Bootstrap 5, Webpack 5
- **Database**: MySQL 8+ / MariaDB 10.11+
- **Search**: OpenSearch 2 / Elasticsearch 8
- **Cache**: Redis (optional), Symfony Cache
- **Testing**: PHPUnit, PHPStan, Jest, Playwright

## Shopware Architecture

### NOT Standard Symfony/Doctrine
- **NO Doctrine ORM** - Uses custom Data Abstraction Layer (DAL)
- **NO QueryBuilder** - Use `Criteria` API instead
- **NO Doctrine Annotations** - Use `EntityDefinition` classes
- **NO Doctrine Repositories** - Use `EntityRepository` with DAL

### Extension Pattern Priority
1. **Prefer Events** - EventSubscriberInterface for most extensibility
2. **Use Decorators Only When** - Event timing doesn't fit

### Three Distinct APIs
- `/api/` - Admin API (full CRUD, admin operations)
- `/store-api/` - Store API (customer-facing, storefront)
- `/api/_action/sync` - Sync API (bulk operations)

## AI Skills

This repo ships Agent Skills under `.agents/skills/`, with `.claude/skills` as a symlink for Claude Code compatibility. Skills are **offered** to the agent and invoked when the task matches their `description` — best-effort and model-decided, **not guaranteed**. The mandatory steps below are therefore stated here, in the always-loaded file, so they apply even when no skill is triggered.

### Definition of Done — mandatory for every change

Before you commit or hand work back:
- **Behaviour change ⇒ tests are required.** Admin JS/TS/Vue → follow `shopware-admin-js`; PHP → `shopware-phpunit-tests`. Style-only, snippet/translation, and docs-only changes do not need tests; still add one when it is useful and follows an established pattern.
- **Writing a PR title or description? → follow `shopware-pr-hygiene`** — the Shopware PR template is required, not a generic one.
- **Behavioural change, feature, deprecation, or config change? → check `shopware-release-docs`** for RELEASE_INFO / UPGRADE entries.
- **Commit with a conventional message incl. scope**, e.g. `feat(administration): …`.
- **After review feedback or CI failures**, create a follow-up commit; do not amend or force-push unless explicitly asked.
- **Lint every file you touched** per the File Linting table below.

When a task matches a skill, open `.agents/skills/<name>/SKILL.md` and follow it **before** implementing.

### Guidance Skills

- `shopware-knowledge-capture` — saving durable knowledge; routing it to AGENTS, coding guidelines, README, ADR, skills, or local notes.
- `shopware-change-scope` — root-cause analysis, boyscouting, and cleanup scope.
- `shopware-release-docs` — release notes, upgrade notes, developer-facing changelog decisions.
- `shopware-pr-hygiene` — PR templates, conventional titles, review follow-up commits.
- `shopware-php-code` — PHP architecture, API schema, migrations, deprecations, BC-sensitive code.
- `shopware-admin-js` — Administration JavaScript, TypeScript, Vue, ACL, Jest.
- `shopware-phpunit-tests` — PHPUnit test structure, fixtures, feature flags, coverage, data providers.

Skills can have an optional unattended twin via [GitHub Agentic Workflows](https://github.com/githubnext/gh-aw) at `.github/workflows/<name>.md` + `.github/aw/<name>-policy.md`. Editing or compiling these workflows requires the `gh aw` CLI extension; the current pin lives in [`.github/aw/README.md`](.github/aw/README.md) → "Pinning".

To add a new skill (interactive or unattended), follow the checklist in [`coding-guidelines/core/agent-skills.md`](coding-guidelines/core/agent-skills.md).

## Subtree Guidance

- PHP/server code: use the `shopware-php-code` skill when the task touches PHP architecture, API schema, migrations, deprecations, or BC-sensitive code.
- Administration JS/TS/Vue code: detailed guidance starts at `src/Administration/Resources/app/administration/AGENTS.md`; use the `shopware-admin-js` skill for Admin coding rules.
- PHPUnit tests: use the `shopware-phpunit-tests` skill.
- More specific nested `AGENTS.md` files add local rules for their subtree.

## Coding Guidelines

**MANDATORY**: All code must follow the guidelines in `coding-guidelines/`.

## File Linting

**MANDATORY**: All code must be linted according to the following table.

| File Type              | Check Command                 | Fix Command                                  |
|------------------------|-------------------------------|----------------------------------------------|
| **PHP** (.php)         | `composer ecs`                | `composer ecs-fix`                           |
| **PHP** (types)        | `composer phpstan`            | N/A - must fix manually                      |
| **JS/TS/Vue** (Admin)  | `composer eslint:admin`       | `composer eslint:admin:fix`                  |
| **JS/TS** (Storefront) | `composer eslint:storefront`  | `composer eslint:storefront:fix`             |
| **SCSS**               | `composer stylelint`          | `composer stylelint:[admin\|storefront]:fix` |
| **Twig** (Storefront)  | `composer ludtwig:storefront` | `composer ludtwig:storefront:fix`            |
| **Snippets**           | `composer translation:lint`   | Manual fix required                          |
| **Prettier** (Admin)   | `composer format:admin`       | `composer format:admin:fix`                  |
