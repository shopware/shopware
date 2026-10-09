---
guide: bc-extension-points
title: Do existing extension points keep working, and do new Store API routes use extension events?
personas: [architecture, maintainer]
rules:
  - { id: BCEXT-001, since: 2026-10-09, source: "adr/2026-09-24-replace-abstract-route-classes-with-extension-events.md#decision", origin: "adr/2026-09-24-replace-abstract-route-classes-with-extension-events.md", fixture: "tests/guide/bc-extension-points/catch" }
  - { id: BCEXT-005, since: 2026-10-09, source: ".agents/skills/shopware-php-code/SKILL.md#structure", origin: "https://github.com/shopware/shopware/pull/18747", fixture: "" }
  - { id: BCEXT-006, since: 2026-10-09, source: "adr/2026-09-24-replace-abstract-route-classes-with-extension-events.md#decision", origin: "https://github.com/shopware/shopware/pull/20978", fixture: "" }
  - { id: BCEXT-009, since: 2026-10-09, source: "coding-guidelines/core/backward-compatibility.md#new-extension-points-stay-open", origin: "https://github.com/shopware/shopware/pull/16877", fixture: "" }
  - { id: BCEXT-010, since: 2026-10-09, source: "coding-guidelines/core/backward-compatibility.md#events-and-decorators", origin: "https://github.com/shopware/shopware/pull/16213", fixture: "" }
---

## Why this guide exists

Every event, abstract class, interface or tag that ships is a contract that plugins build on and that we must keep working for years. The 6.8 stability direction treats new public surface with the same scrutiny as a break. On a `6.x` base branch an extension point that ships cannot be taken back before the next major; on `trunk` it becomes part of the next major's promise. This guide covers changes to existing extension points, new Store API routes and what plugins can reach through a new seam; the design of new services and seams (contract shape, hot paths, consumer, `ResetInterface`, event payload, decorator forwarding) lives in the di-and-extension-points guide.

## Check

- **BCEXT-001** A new Store API route gets an abstract route class with `getDecorated()`, or its method is added to the allowlists in `store-api-route-extensions.neon` so PHPStan stays quiet. Plugins then decorate the route, and the class can never become `@internal`. New routes publish an extension event instead. Rule: [ADR extension events for Store API routes](../../../../adr/2026-09-24-replace-abstract-route-classes-with-extension-events.md#decision). Example: the ADR's `ActiveCountryRoute` with `ActiveCountryRouteExtension`.
- **BCEXT-005** A Store API route payload or an extension event carries the whole `Request` instead of the values it needs. Plugins then read arbitrary request data, and the payload cannot be built outside an HTTP request. Put the needed values (for example the client IP) into the payload. Rule: [Structure](../../shopware-php-code/SKILL.md#structure). Example: #18747.
- **BCEXT-006** A public property of an existing extension event (or its `NAME`) is renamed, retyped or removed, or the constructor is treated as the contract. Listeners read those properties; the constructor is `@internal` and owned by Shopware, so add properties instead of changing them. Rule: [ADR, Decision](../../../../adr/2026-09-24-replace-abstract-route-classes-with-extension-events.md#decision). Example: #20978 introduced the route extension events.
- **BCEXT-009** A new abstraction that only core can use: an interface with a closed enum third parties cannot extend, a typed request DTO plugins cannot add fields to, a provider that lets extensions add defaults but not remove them, or a feature where extensions can add parts (a form, a document type) but not the data that belongs to them. Plugin authors hit a wall and fall back to decorating internals. Use a registry or tagged services, `Struct` extensions and a core provider for the defaults. Rule: [New extension points stay open](../../../../coding-guidelines/core/backward-compatibility.md#new-extension-points-stay-open). Example: #16877 (closed `UrlType` enum in `UrlProviderInterface`), #17858 (`ChangeProfileRequest`), #20323 (CORS headers extensions cannot remove).
- **BCEXT-010** A reworked or reordered flow changes what listeners and decorators see: an event dispatched at another point, a value derived before the extension event that can still change its input, a reset that wipes what listeners added, a type hint narrowed from the abstract base to the concrete core class, or an exact class check (`$x::class === Foo::class`). Plugins keep compiling, but their listener or decorator silently stops having an effect. Rule: [Events and decorators](../../../../coding-guidelines/core/backward-compatibility.md#events-and-decorators). Example: #16213 (`SortingListingProcessor` resets listener sortings and type-hints the concrete class), #19681 (VAT country derived before the mapping event), #19940 (exact class check in `FlowExecutor`).

## Do not flag

### CI covers

- `StoreApiRouteExtensionRule`: a new Store API route method that has `getDecorated()`, overrides an abstract parent, or does not return `ExtensionDispatcher::publish()`. Flag only new allowlist entries (BCEXT-001).
- `ExtensionRule`: extension classes are `final`, not `@internal`, have a public `NAME` and an `@internal` constructor.
- `DecorationPatternRule`: abstract decorator bases are not `@internal` or `@final`, implementations add no extra public methods and are no subscribers. `NoRouteOverrideInDecoratorsRule`: no copied `#[Route]` in decorators.
- `TaggedServiceContractRule`: tagged services implement the configured tag contract.
- `ApiRoutesHaveASchemaTest` and Danger `RouteSnapshotExtension`: a new route without OpenAPI schema. A struct field missing from the schema is owned by the bc-api-contracts guide.

### Legitimate patterns

- Existing routes that keep their abstract class (already in the allowlist), and new non-abstract methods on decorator bases.
- New extension events for new or adjusted routes; new properties on existing extension events.
- `@internal` seams and test code.
- Design of a new seam (interface versus abstract class, hot-path dispatch, a seam with one consumer, mutable state without `ResetInterface`, entities in notification events, static helpers, `getDecorated()` forwarding): the di-and-extension-points guide owns it (DIX-001 to DIX-007).

## Severity

- `blocking`: a public property or `NAME` of an existing event is removed or changed in a minor. Listeners in plugins fail after a routine update.
- `major`, `requires_human: true`: new public surface of the wrong kind (abstract route class, `Request` in a payload, an abstraction plugins cannot extend). It becomes a contract for years; a person decides whether it is wanted.
- `major`: a reworked flow in which plugin listeners or decorators silently stop having an effect (BCEXT-010).

## Retired

- BCEXT-002 (2026-10-09): duplicate of DIX-003 in di-and-extension-points.md.
- BCEXT-003 (2026-10-09): duplicate of DIX-004 in di-and-extension-points.md.
- BCEXT-004 (2026-10-09): duplicate of DIX-001 in di-and-extension-points.md.
- BCEXT-007 (2026-10-09): duplicate of DIX-006 in di-and-extension-points.md.
- BCEXT-008 (2026-10-09): duplicate of DIX-005 in di-and-extension-points.md.
