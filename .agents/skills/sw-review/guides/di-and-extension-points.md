---
guide: di-and-extension-points
title: Are new services and extension points shaped right for the people who will extend them?
personas: [architecture]
rules:
  - { id: DIX-001, since: 2026-10-09, source: "coding-guidelines/core/decorator-pattern.md#how-to-use-the-decorator-pattern", origin: "https://github.com/shopware/shopware/pull/18747", fixture: "" }
  - { id: DIX-002, since: 2026-10-09, source: "coding-guidelines/core/decorator-pattern.md#example", origin: "https://github.com/shopware/shopware/pull/17483", fixture: "" }
  - { id: DIX-003, since: 2026-10-09, source: "coding-guidelines/core/backward-compatibility.md#public-api-is-every-extension-surface", origin: "https://github.com/shopware/shopware/pull/21391", fixture: "tests/guide/di-and-extension-points/catch" }
  - { id: DIX-004, since: 2026-10-09, source: "coding-guidelines/core/backward-compatibility.md#public-api-is-every-extension-surface", origin: "https://github.com/shopware/shopware/pull/21385", fixture: "" }
  - { id: DIX-005, since: 2026-10-09, source: "adr/2022-03-09-reset-class-state-during-requests.md#decision", origin: "https://github.com/shopware/shopware/pull/17733", fixture: "" }
  - { id: DIX-006, since: 2026-10-09, source: "coding-guidelines/core/extendability.md#mediator", origin: "https://github.com/shopware/shopware/pull/18610", fixture: "" }
  - { id: DIX-007, since: 2026-10-09, source: ".agents/skills/shopware-php-code/SKILL.md#structure", origin: "https://github.com/shopware/shopware/pull/17075", fixture: "tests/guide/di-and-extension-points/catch" }
---

## Why this guide exists

Services, decorators, tags and events are how plugins change Shopware without forking it. A new extension point of the wrong shape becomes a contract we cannot change for years; a service that keeps state leaks data between requests in long-running workers and, in the cloud, between tenants. This guide covers the design of new services and new extension points; the bc-extension-points guide covers changes to the ones that already shipped, new Store API routes and seams plugins cannot extend.

## Check

- **DIX-001** Look at the shape of a new replaceable service. One service that plugins should decorate gets an abstract class with `getDecorated()`; several providers side by side get an interface plus a tag or registry; an implementation detail gets neither and stays `@internal`. An interface cannot grow without breaking every implementation, and an abstract class without decoration is a contract with no user. Rule: [How to use the decorator pattern](../../../../coding-guidelines/core/decorator-pattern.md#how-to-use-the-decorator-pattern), [Adapter](../../../../coding-guidelines/core/extendability.md#adapter). Example: #18747 (abstract class without decoration, should be an interface), #17742 (interface where decoration was intended).
- **DIX-002** Look at a new method on an existing abstract decorator base, and at new decorators. The base method must be non-abstract and forward to `getDecorated()`, and a decorator forwards every call it does not change to the inner service. Otherwise every plugin decorator breaks, or the next decorator in the chain is skipped. Rule: [Decorator pattern example](../../../../coding-guidelines/core/decorator-pattern.md#example). Example: #17483 (`AbstractDomainLoader`).
- **DIX-003** Look for a new event, extension or hook that an existing one already covers (a near-duplicate with another payload, an event for one edge case, a second way to do the same thing) or that is dispatched in a central hot path: `EntitySearcher`, `EntityReader`, `RequestCriteriaBuilder`, kernel or request listeners. Every request of every shop pays for the dispatch, one slow listener slows down the whole shop, and plugin authors must pick between two mechanisms that core keeps forever. Reuse the existing Store API route extension events, enrich the existing event, or add one generic event that names its source. Rule: [Public API is every extension surface](../../../../coding-guidelines/core/backward-compatibility.md#public-api-is-every-extension-surface); the hot-path part: guideline paragraph pending. Example: #21391 (an event in `RequestCriteriaBuilder`), #17252 (a stock event duplicating the existing ones), #17124 (a second, narrower extension-loader event).
- **DIX-004** Look for a new public interface, event or generic capability without a named consumer, or with only one internal consumer. Plugins start using it, and its shape is frozen for a case nobody needed. Change the consumer, keep the seam `@internal`, or add the point with its first real use. Rule: [Public API is every extension surface](../../../../coding-guidelines/core/backward-compatibility.md#public-api-is-every-extension-surface). Example: #21385, #17296 (route decoration already covered the case), #16296.
- **DIX-005** Look for services that memoize data in a property (a cache array, a loaded config, a counter) without `ResetInterface` or the `kernel.reset` tag. In long-running workers the data leaks into the next request or message, in the cloud into the next tenant. Rule: [ADR reset class state](../../../../adr/2022-03-09-reset-class-state-during-requests.md#decision). Example: #17733, #19199, #21186.
- **DIX-006** Look at the payload of a new notification event: it should carry ids and the context consumers need (version id, type name), not whole entities, and not extend Symfony `Event` unless stopping propagation is intended; an extension event instead carries the input objects listeners may change. Entities freeze the entity shape into the event and block asynchronous handling. Rule: [Mediator](../../../../coding-guidelines/core/extendability.md#mediator), [Extension events](../../../../coding-guidelines/core/extendability.md#extension-events). Example: #18610, #16296, #21165.
- **DIX-007** Look for a new static helper class that does work a DI service already does or should do (rounding, config, formatting with dependencies), called from a service. Plugins cannot decorate or replace it, and tests cannot stub it. Inject the service through the constructor. Rule: [PHP code, Structure](../../shopware-php-code/SKILL.md#structure); guideline paragraph pending. Example: #17075.

## Do not flag

### CI covers

- `DecorationPatternRule` and `AbstractClassUsageRule` (missing `getDecorated()`, extra public methods, subscribers as decorators, concrete type hints for decoratable services), `PublicServiceDecoratorRule`, `NoRouteOverrideInDecoratorsRule`, `TaggedServiceContractRule`, `ExtensionRule`, `StoreApiRouteExtensionRule`.
- `NoNativeTimeFunctionRule` and `NoNativeTimeClassRule`: native time reads instead of an injected `ClockInterface`.

### Legitimate patterns

- Static helpers that are pure functions without dependencies (string or array utilities). Static helper versus service is contested among reviewers (#17075 versus #16721); flag DIX-007 only when a service for the same job exists or the helper needs dependencies.
- `@internal` services and seams, test doubles, and new events on code that is not called per request.
- Changes to existing points (renamed event properties, reworked flows that bypass listeners or decorators), new Store API routes (`Abstract*Route` versus extension event), `Request` in a payload and seams plugins cannot extend belong to the bc-extension-points guide (BCEXT-001, -005, -006, -009, -010).

## Severity

- `major`, `requires_human: true`: a new public extension point of the wrong shape, in a hot path, or without a consumer. It becomes a contract for years; a person decides whether it is wanted.
- `major`: mutable state without `ResetInterface`, or a decorator base method that is abstract. Data leaks between requests, or every plugin decorator breaks.
- `minor`: an event payload with entities, or a static helper where a service exists.

## Retired

(none yet)
