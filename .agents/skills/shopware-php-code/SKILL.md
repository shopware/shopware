---
name: shopware-php-code
description: Apply Shopware PHP/server-side coding guidance. Use when editing PHP under src/Core, src/Administration, or src/Storefront — including migrations, API schema, deprecations, or BC-sensitive code.
license: MIT
---

# Shopware PHP Code

Prefer the existing Shopware extension point over a new abstraction.

## Structure

- Keep application/domain services hexagonal: controllers, CLI commands, subscribers, and handlers translate infrastructure details (`Request`, IO, database, filesystem, HTTP) into plain inputs before calling services.
- Services must not perform direct infrastructure work or depend on framework objects. Depend on narrow abstractions instead, such as repositories, filesystem interfaces, HTTP clients, or gateways.
- Services must be unit-testable without external systems; test infrastructure adapters with integration tests.
- Mark infrastructure adapters `@internal` by default.
- Mark supported/public concrete classes as `@final` when they are not intended for extension.
- Use a real `final class` for simple value objects/structs that do not need extension, decoration, or mocking; use `@final` for supported services where tests or framework mechanics may still need to subclass/mock them.
- Do not add `@final` to classes already marked `@internal`; the internal marker is enough for implementation details.
- Do not repeat `@internal` on constructors or methods inside an `@internal` class.
- Prefer existing Shopware extension mechanisms over new provider interfaces when they already express the contract, for example Twig inheritance, DAL entities, Admin API routes, or explicit Twig blocks.
- Be conservative with DTOs/value objects. Add one only when it expresses a meaningful domain concept, crosses a real boundary, or simplifies a public contract. Prefer scalars or arrays for simple internal data, and do not create DTOs solely to model private handoffs inside one class.
- For transparent struct-style value objects, prefer public readonly properties over private properties plus trivial getters.

## Public Surface

- For new feature designs, explicitly separate the BC-promised public surface from internal implementation services. Document public REST/Admin/Store API contracts, DAL entities, template context, and supported extension points; mark controllers, subscribers, loaders, renderers, and discovery services `@internal` unless they are intended extension points.

## API Schema

- When adding core Admin API or Store API action routes, add the matching OpenAPI JSON schema under `src/Core/Framework/Api/ApiDefinition/Generator/Schema/<AdminApi|StoreApi>/paths`.
- Run `tests/integration/Core/Framework/ApiRoutesHaveASchemaTest.php` for new or changed core API routes to catch missing paths, method mismatches, and stale schema entries.
- The HTTP route contract can be public even when the PHP controller class is internal; document those separately.

## Deprecations

- Core code should never trigger self-deprecation notices. If core must keep calling deprecated behavior for BC, wrap that call with `Feature::silent($majorFlag, static fn () => ...)` so the deprecation notice is suppressed, the code path is explicitly tied to the major feature flag, and the branch will disappear when the flag is removed.
- Do not mark DI service definitions as deprecated while Shopware core still references that service id anywhere. Internal DI references still trigger container deprecations and spam logs during warmup/compile. Deprecate the PHP API or class if needed, but only add the DI `<deprecated>` service tag once core no longer uses that service internally.
- When adding an `@deprecated` annotation to executable PHP code, add a matching `Feature::triggerDeprecationOrThrow()` in the deprecated code path unless the deprecation uses an explicit exception reason supported by the PHPStan deprecation rule.
- Do not leave new Shopware core code paths calling deprecated functionality. Move internal callers to the replacement API/service and keep legacy behavior only in focused BC tests.
- For private implementation cleanup reminders, do not add method-level deprecations. Use a short inline `// @deprecated tag:vX.Y.Z - ...` comment near the branch or code that should be removed later, with enough detail to simplify the future cleanup.
- When adding a temporary BC/deprecation branch for future feature-flagged behavior, guard it with the relevant `Feature::isActive(...)` check so the new path already exists, can be toggled, and the deprecated branch can be removed directly when the flag is removed.
- If a deprecated API remains for BC, add or keep dedicated legacy tests that are easy to remove with the deprecation. Guard them for the relevant major feature flag when needed.
- For any developer-facing deprecation or upcoming BC break, document both the currently available replacement and the future break/removal: use `RELEASE_INFO-6.<minor>.md` to explain the new replacement, why the old behavior/API is deprecated, and who is affected; use `UPGRADE-6.<next-major>.md` to explain what will break or be removed and the concrete migration steps.
- If both REST/Admin/Store API contracts and PHP-level APIs or extension points are affected, document them as separate entries in the relevant sections, for example API for REST routes and Core for services, interfaces, abstract classes, decorators, or extension points.
- In both release notes and upgrade guides, write from the perspective of extension authors, API consumers, operators, or other outside users. Include whether adjacent APIs remain unchanged when that distinction prevents migration mistakes.

## Migrations

- Use the exact current Unix timestamp for new migration class names, file names, and `getCreationTimestamp()` values. Do not use placeholder or rounded timestamps.
- Do not add tests for empty/no-op `updateDestructive()` implementations; cover meaningful migration behavior in `update()` or destructive migrations that actually change state.

## Detailed Guidelines

- Read `coding-guidelines/core/internal.md` and `coding-guidelines/core/final-and-internal.md` when marking PHP API surface as internal, final, or supported for extension.
- Read `coding-guidelines/core/extendability.md` and `coding-guidelines/core/decorator-pattern.md` when adding or changing extension points.
- Read `coding-guidelines/core/database-migations.md` when adding or changing migrations.
- Read `coding-guidelines/core/feature-flags.md` when adding feature-flagged behavior, deprecations, or BC branches.
