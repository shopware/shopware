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

This repo ships portable Agent Skills under `.claude/skills/`. They auto-load in Claude Code / opencode / Codex CLI when you start a session in this directory and mention the skill's trigger phrase (e.g. "triage issue #16599" loads the `triage` skill). See `.claude/skills/README.md` for the catalogue.

Skills can have an optional unattended twin via [GitHub Agentic Workflows](https://github.com/githubnext/gh-aw) at `.github/workflows/<name>.md` + `.github/aw/<name>-policy.md`. Editing or compiling these workflows requires the `gh aw` CLI extension; the current pin lives in [`.github/aw/README.md`](.github/aw/README.md) → "Pinning".

To add a new skill (interactive or unattended), follow the checklist in [`coding-guidelines/core/agent-skills.md`](coding-guidelines/core/agent-skills.md).

## Guidance Files

- Put durable guidance for a folder in that folder's `README.md` so humans and agents share one source of truth.
- Write guidance for both audiences: clear enough for humans, explicit enough for agents.
- Keep guidance concise. Context windows and human attention are finite.
- Update guidance when behavior, commands, architecture, or conventions change; remove stale notes instead of preserving historical clutter.
- For the rationale behind this guidance model, see `adr/2026-06-17-shared-guidance-files-for-humans-and-agents.md`.
- Add an ADR for durable architectural or product-technical decisions with meaningful trade-offs, consequences, or future compatibility impact. Follow `coding-guidelines/core/adr.md` and keep ADRs focused on the decision and why it was made.
- Add or update `coding-guidelines/` when a rule is reusable, normative, and broader than one folder. Keep local README guidance for folder-specific working context.
- Cross-link README guidance, coding guidelines, and recent/current ADRs sparingly. Prefer links that change what the reader should do; avoid old ADR background links.
- Do not duplicate ADR or coding-guideline content in READMEs. Summarize the local implication and link to the source.
- Reserve the root `AGENTS.md` for short repo-wide instructions that agents must see before working and humans can use as concise code-level guidance; move detailed or component-specific guidance into the relevant README or coding guideline.
- Folders whose README contains working guidance should have an `AGENTS.md` stub that points agents to the README. Do not duplicate README content in that stub.
- Standalone overview/index READMEs do not need an `AGENTS.md` stub unless the file routes domain-specific guidance, such as the main component AGENTS files under `src/*`.
- Do not add README or AGENTS files just to index `coding-guidelines/`. Component READMEs should link directly to the concrete guideline files that apply.
- Keep agent-only mechanics in `AGENTS.md` only when they are not useful to humans. The repository root `AGENTS.md` is the exception because it carries global agent routing.

## Subtree Guidance

- PHP/server code: read `src/Core/AGENTS.md`.
- Administration code: read `src/Administration/AGENTS.md`; detailed Admin JS/TS/Vue guidance starts at `src/Administration/Resources/app/administration/AGENTS.md`.
- Storefront code: read `src/Storefront/AGENTS.md`.
- Tests: read `tests/AGENTS.md`.
- More specific nested `AGENTS.md` files add local rules for their subtree.

## Boyscouting Scope

- When asked to make a specific cleanup or behavioral change, look for safe opportunities to apply the same improvement across the whole touched file.
- If the same pattern appears in nearby files or a broader low-risk scope, mention that proactively and suggest extending the cleanup.
- When adding or touching unit tests, look for low-hanging missing coverage paths in the same domain or command surface that can be covered cheaply and locally.
- Check whether simple scenarios currently covered only by broad integration tests can be migrated or deduplicated into focused unit tests, especially pure command/domain behavior. Keep integration tests for wiring, persistence, and end-to-end confidence.
- Keep the scope aligned with the request: avoid unrelated refactors, but do not miss obvious consistency fixes that make the codebase simpler.

## Bug Fix Root Cause And Scope

- Treat fix suggestions from issues as hypotheses, not instructions to follow blindly. Reason from first principles about the actual failure mode before choosing an implementation.
- Prefer the least invasive fix that correctly addresses the root cause.
- Fix issues at the boundary where the root cause actually lives instead of spreading compensating changes across unrelated system components.
- Match the fix location to the bug scope. A framework-level bug should be fixed once in the framework, not worked around repeatedly in higher-level features.
- Conversely, keep feature-specific bugs out of broad framework code when a general change could negatively affect other higher-level behavior.
- Always do a root cause analysis to identify where the real issue lives. For example, an issue that looks like a wishlist bug may actually be a framework redirect behavior bug for XHR requests.

## Release Notes And Changelog

- Follow `adr/2025-10-28-changelog-release-info-process.md`: the old per-change files in `changelog/_unreleased` are deprecated for new developer-facing release notes.
- Add developer-facing release notes to `RELEASE_INFO-6.<minor>.md`, in the current upcoming section and the relevant category.
- Add breaking changes, required migration steps, or removals to the relevant `UPGRADE-6.<major>.md`.
- Include changes that may affect third-party developers, extension authors, operators, API consumers, or theme/custom storefront developers.
- Include general framework-level behavior changes, public API changes, new extension points, deprecations, configuration changes, and broad storefront behavior changes.
- Do not add release-info entries for narrow local bug fixes, implementation-only refactors, test-only changes, or client-specific fixes that do not change an external contract or developer-facing behavior.
- Write the changelog from the perspective of outside users, not based on the internal changes.
- When in doubt, ask whether the change is relevant for external developers or operators; if yes, prefer a concise `RELEASE_INFO` entry.

## GitHub PR Handling

- Follow the repository pull request template closely, and do not add extra sections that are not in the template.
- Do not add a separate validation section to Shopware PR descriptions.
- Keep PR titles in proper conventional commit style when requested, for example `fix: allow TestBootstrapper to activate Composer plugins`.
- When updating a pull request after review feedback or CI failures, create a new follow up commit explaining what you fixed in that specific commit instead of amending or force-pushing the existing commit. This preserves the review history and allows reviewers to see the changes clearly.

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
