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
- Cross-link README guidance, coding guidelines, and ADRs where the link helps readers understand what to do and why. Prefer: README links to applicable concrete coding guidelines and ADRs; coding guidelines link to ADRs for decision background; ADRs link to follow-up docs only when needed to find the living rules.
- Do not duplicate ADR or coding-guideline content in READMEs. Summarize the local implication and link to the source.
- Reserve the root `AGENTS.md` for short repo-wide instructions that agents must see before working; move detailed or component-specific guidance into the relevant README or coding guideline.
- Folders whose README contains working guidance should have an `AGENTS.md` stub that points agents to the README. Do not duplicate README content in that stub.
- Standalone overview/index READMEs do not need an `AGENTS.md` stub. This includes the main component READMEs under `src/*` and folders whose path contains `docs`.
- Do not add README or AGENTS files just to index `coding-guidelines/`. Component READMEs should link directly to the concrete guideline files that apply.
- Keep agent-only mechanics in `AGENTS.md` only when they are not useful to humans. The repository root `AGENTS.md` is the exception because it carries global agent routing.

## Administration ACL

- Admin UI changes that read or persist DAL entities or associations must update matching ACL privilege mappings and migrations for existing roles when needed; see `coding-guidelines/administration/architecture.md`.

## PHP Code Structure

- Keep application/domain services hexagonal: controllers, CLI commands, subscribers, and handlers translate infrastructure details (`Request`, IO, database, filesystem, HTTP) into plain inputs before calling services.
- Services must not perform direct infrastructure work or depend on framework objects. Depend on narrow abstractions instead, such as repositories, filesystem interfaces, HTTP clients, or gateways.
- Services must be unit-testable without external systems; test infrastructure adapters with integration tests.
- Mark infrastructure adapters `@internal` by default.
- Mark services `@private` by default when third-party code may call them but must not extend, decorate, or rely on their internals. Use explicit public extension points for supported customization.
- Mark supported/public concrete classes as `@final` when they are not intended for extension.
- Use a real `final class` for simple value objects/structs that do not need extension, decoration, or mocking; use `@final` for supported services where tests or framework mechanics may still need to subclass/mock them.
- Do not add `@final` to classes already marked `@internal`; the internal marker is enough for implementation details.
- Do not repeat `@internal` on constructors or methods inside an `@internal` class.
- Prefer existing Shopware extension mechanisms over new provider interfaces when they already express the contract, for example Twig inheritance, DAL entities, Admin API routes, or explicit Twig blocks.
- For new feature designs, explicitly separate the BC-promised public surface from internal implementation services. Document public REST/Admin/Store API contracts, DAL entities, template context, and supported extension points; mark controllers, subscribers, loaders, renderers, and discovery services `@internal` unless they are intended extension points.
- Be conservative with DTOs/value objects. Add one only when it expresses a meaningful domain concept, crosses a real boundary, or simplifies a public contract. Prefer scalars or arrays for simple internal data, and do not create DTOs solely to model private handoffs inside one class.
- For transparent struct-style value objects, prefer public readonly properties over private properties plus trivial getters.

## Unit Test Structure

- Write test methods as clear executable examples of the behavior under test: scenario-specific setup, action, and assertions should be easy to follow in the test body.
- Prefer explicit scenario setup over hidden mutation in fixture factories. Helper methods should create entities, files, or value objects; the test body should perform meaningful scenario wiring when that wiring helps explain the behavior under test.
- Move stable boilerplate such as mock services, the class under test, command testers, and temporary project directories into `setUp()` / `tearDown()` when that lets concrete tests focus on the scenario-specific data and execution.
- Put reusable fixture collaborators in `setUp()` when helper methods or getters may be called more than once in a test and callers should observe the same instance or state, for example registries, containers, command testers, shared filesystem roots, or other idempotent lookup objects. Keep per-scenario mutations in the test body or explicit helper parameters, but do not hide repeated construction in a getter when identity or accumulated setup matters.
- For unit tests around file access, choose the lightest setup that still reads naturally: simple single-file reads/writes can use Symfony `Filesystem` injected into the class and mocked in the test; when the scenario needs several consecutive filesystem calls, realistic paths, or directory structure, prefer committed `_fixtures` over building temp files at runtime or over-mocking the filesystem.
- Keep test helpers smaller than the code they replace. Do not hide assertions or feature-flag toggling behind abstractions when direct assertions are just as readable.
- Prefer one focused test method per distinct exception or behavior over broad data providers when each case has its own meaning.
- In PHPUnit tests, prefer `expectExceptionObject()` over `expectExceptionMessage()` / `expectExceptionMessageMatches()` because message expectations are deprecated in future PHPUnit versions. Build the expected exception through the same domain factory when one exists so class, code, and message stay aligned with production behavior.
- Keep legacy feature-flag behavior in dedicated tests that are easy to remove when the flag is removed.
- In unit tests, current major feature flags are active by default. Test legacy/off behavior by disabling the flag with the `#[DisabledFeatures]` attribute; do not use `Feature::fake()` just to activate the current major flag.
- In integration tests, the suite may run multiple times with feature flags on and off. Do not use `#[DisabledFeatures]` there for simple legacy/current branching; skip tests explicitly with `Feature::skipTestIfActive()` or `Feature::skipTestIfInActive()` when the current feature-flag value is not the one the scenario expects.
- Do not mock Doctrine DBAL `Connection` in unit tests. Isolate SQL/DBAL work in dedicated database adapters and cover those adapters with integration tests.
- If a class is intentionally covered only by integration tests, mark it with `@codeCoverageIgnore` on its own docblock line and add a separate `@see ShortIntegrationTestClassName` line. Import the integration test class with a `use` statement instead of writing a fully-qualified class name in the annotation.
- Every new class should either have focused unit-test coverage or be explicitly marked with `@codeCoverageIgnore` and an integration-test `@see` when unit coverage does not make sense.
- Simple struct-style classes with only public properties do not need unit tests; mark them with `@codeCoverageIgnore` instead.
- Do not add `#[CoversClass]`, `#[CoversFunction]`, or `#[CoversNothing]` to integration tests. Shopware's PHPStan rule allows those attributes only on unit and migration tests.

### Data Providers

- Use named `yield` cases in unit-test data providers instead of returning arrays, even for small providers. This keeps cases readable and avoids materializing large arrays as providers grow.
- Do not use `yield from` with an inline array for providers. Prefer one explicit `yield 'human readable case description' => [...]` per scenario.
- Provider case names should explain the scenario and expected behavior, not mechanically restate raw input values. Good names mention the rule being proven, such as priority, normalization, timezone conversion, or boundary handling.
- Be conservative when deleting "duplicate" provider cases. Remove only exact semantic duplicates that add no coverage, and keep similar-looking cases when they cover distinct edge behavior.

## Migration Structure

- Use the exact current Unix timestamp for new migration class names, file names, and `getCreationTimestamp()` values. Do not use placeholder or rounded timestamps.
- Do not add tests for empty/no-op `updateDestructive()` implementations; cover meaningful migration behavior in `update()` or destructive migrations that actually change state.

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

## API Schema

- When adding core Admin API or Store API action routes, add the matching OpenAPI JSON schema under `src/Core/Framework/Api/ApiDefinition/Generator/Schema/<AdminApi|StoreApi>/paths`.
- Run `tests/integration/Core/Framework/ApiRoutesHaveASchemaTest.php` for new or changed core API routes to catch missing paths, method mismatches, and stale schema entries.
- The HTTP route contract can be public even when the PHP controller class is internal; document those separately.

## Deprecations And BC Documentation

- Core code should never trigger self-deprecation notices. If core must keep calling deprecated behavior for BC, wrap that call with `Feature::silent($majorFlag, static fn () => ...)` so the deprecation notice is suppressed, the code path is explicitly tied to the major feature flag, and the branch will disappear when the flag is removed.
- When adding an `@deprecated` annotation to executable PHP code, add a matching `Feature::triggerDeprecationOrThrow()` in the deprecated code path unless the deprecation uses an explicit exception reason supported by the PHPStan deprecation rule.
- Do not leave new Shopware core code paths calling deprecated functionality. Move internal callers to the replacement API/service and keep legacy behavior only in focused BC tests.
- For private implementation cleanup reminders, do not add method-level deprecations. Use a short inline `// @deprecated tag:vX.Y.Z - ...` comment near the branch or code that should be removed later, with enough detail to simplify the future cleanup.
- When adding a temporary BC/deprecation branch for future feature-flagged behavior, guard it with the relevant `Feature::isActive(...)` check so the new path already exists, can be toggled, and the deprecated branch can be removed directly when the flag is removed.
- If a deprecated API remains for BC, add or keep dedicated legacy tests that are easy to remove with the deprecation. Guard them for the relevant major feature flag when needed.
- For any developer-facing deprecation or upcoming BC break, document both the currently available replacement and the future break/removal: use `RELEASE_INFO-6.<minor>.md` to explain the new replacement, why the old behavior/API is deprecated, and who is affected; use `UPGRADE-6.<next-major>.md` to explain what will break or be removed and the concrete migration steps.
- If both REST/Admin/Store API contracts and PHP-level APIs or extension points are affected, document them as separate entries in the relevant sections, for example API for REST routes and Core for services, interfaces, abstract classes, decorators, or extension points.
- In both release notes and upgrade guides, write from the perspective of extension authors, API consumers, operators, or other outside users. Include whether adjacent APIs remain unchanged when that distinction prevents migration mistakes.

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
