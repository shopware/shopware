---
name: shopware-php-code
description: Apply Shopware PHP/server-side coding guidance. Use when editing PHP application, domain, framework, migration, API schema, deprecation, or BC-sensitive code under src/Core, src/Administration PHP, src/Storefront PHP, or PHP tests that exercise those contracts.
---

# Shopware PHP Code

Prefer the existing Shopware extension point over a new abstraction.

## Structure

- Keep services hexagonal: controllers, commands, subscribers, and handlers translate framework/input details into plain inputs before calling services.
- Keep services unit-testable without external systems; isolate database/filesystem/HTTP work behind narrow adapters.
- Mark infrastructure adapters `@internal` by default.
- Use `@final` for supported concrete classes not intended for extension; use real `final class` for simple value objects that do not need mocking, decoration, or extension.
- Do not add `@final` inside `@internal` implementation details.
- Prefer public readonly properties for transparent struct-style value objects.
- Add DTOs only when they express a real domain concept, cross a boundary, or simplify a public contract.

## Public Surface

- For new feature designs, separate BC-promised public surface from internal implementation.
- Document public REST/Admin/Store API contracts, DAL entities, template context, and supported extension points.
- Keep controllers, subscribers, loaders, renderers, and discovery services internal unless they are intended extension points.
- When adding core Admin API or Store API action routes, add the matching OpenAPI JSON schema under `src/Core/Framework/Api/ApiDefinition/Generator/Schema/<AdminApi|StoreApi>/paths`.

## Deprecations

- Core code must not trigger its own deprecation notices; wrap unavoidable legacy calls with `Feature::silent($majorFlag, static fn () => ...)`.
- Add `Feature::triggerDeprecationOrThrow()` when adding executable `@deprecated` code unless the PHPStan deprecation rule supports the explicit reason.
- Move internal callers to replacement APIs; keep legacy behavior only in focused BC tests.
- Use inline `// @deprecated tag:vX.Y.Z - ...` comments for private future cleanup branches, not method-level deprecations.

## Migrations

- Use the exact current Unix timestamp for new migration class names, file names, and `getCreationTimestamp()`.
- Do not test empty `updateDestructive()` methods.
