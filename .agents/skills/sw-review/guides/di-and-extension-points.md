---
guide: di-and-extension-points
title: Are new services and extension points shaped right for the people who will extend them?
personas: [architecture]
rules:
  - { id: DIX-001, since: 2026-10-09, source: "coding-guidelines/core/decorator-pattern.md#how-to-use-the-decorator-pattern", fixture: "" }
  - { id: DIX-002, since: 2026-10-09, source: "coding-guidelines/core/decorator-pattern.md#example", fixture: "" }
  - { id: DIX-003, since: 2026-10-09, source: "coding-guidelines/core/backward-compatibility.md#public-api-is-every-extension-surface", fixture: "tests/guide/di-and-extension-points/catch" }
  - { id: DIX-004, since: 2026-10-09, source: "coding-guidelines/core/backward-compatibility.md#public-api-is-every-extension-surface", fixture: "" }
  - { id: DIX-005, since: 2026-10-09, source: "adr/2022-03-09-reset-class-state-during-requests.md#decision", fixture: "" }
  - { id: DIX-006, since: 2026-10-09, source: "coding-guidelines/core/extendability.md#mediator", fixture: "" }
  - { id: DIX-007, since: 2026-10-09, source: ".agents/skills/shopware-php-code/SKILL.md#structure", fixture: "tests/guide/di-and-extension-points/catch" }
---

## Why this guide exists

Services, decorators, tags and events are how plugins change Shopware without forking it. A new extension point of the wrong shape becomes a contract we cannot change for years; a service that keeps state leaks data between requests in long-running workers and, in the cloud, between tenants. This guide covers the design of new services and new extension points; the bc-extension-points guide covers changes to the ones that already shipped, new Store API routes and seams plugins cannot extend.

## Check

- **DIX-001** Look at the shape of a new replaceable service. One service that plugins should decorate gets an abstract class with `getDecorated()`; several providers side by side get an interface plus a tag or registry; an implementation detail gets neither and stays `@internal`. An interface cannot grow without breaking every implementation, and an abstract class without decoration is a contract with no user. Rule: [How to use the decorator pattern](../../../../coding-guidelines/core/decorator-pattern.md#how-to-use-the-decorator-pattern), [Adapter](../../../../coding-guidelines/core/extendability.md#adapter). Example: the cookie consent log storage was an abstract class without decoration and should have been an interface; the product stream criteria enricher used an interface where decoration was intended.
- **DIX-002** Look at a new method on an existing abstract decorator base, and at new decorators. The base method must be non-abstract and forward to `getDecorated()`, and a decorator forwards every call it does not change to the inner service. Otherwise every plugin decorator breaks, or the next decorator in the chain is skipped. Rule: [Decorator pattern example](../../../../coding-guidelines/core/decorator-pattern.md#example). Example: a method on `AbstractDomainLoader` was deprecated without following the decorator guideline, so plugin decorators had no forwarding path to the new method.
- **DIX-003** Look for a new event, extension or hook that an existing one already covers (a near-duplicate with another payload, an event for one edge case, a second way to do the same thing) or that is dispatched in a central hot path: `EntitySearcher`, `EntityReader`, `RequestCriteriaBuilder`, kernel or request listeners. Every request of every shop pays for the dispatch, one slow listener slows down the whole shop, and plugin authors must pick between two mechanisms that core keeps forever. Reuse the existing Store API route extension events, enrich the existing event, or add one generic event that names its source. Rule: [Public API is every extension surface](../../../../coding-guidelines/core/backward-compatibility.md#public-api-is-every-extension-surface); the hot-path part: guideline paragraph pending. Example: a new event in `RequestCriteriaBuilder` would have run on every request although the Store API route extension events cover the case; a new stock event duplicated the existing ones with another payload; the extension loader got a second, narrower event where one event naming its source would do.
- **DIX-004** (proposed policy) Look for a new public interface, event or generic capability without a named consumer, or with only one internal consumer. Plugins start using it, and its shape is frozen for a case nobody needed. Change the consumer, keep the seam `@internal`, or add the point with its first real use. Rule: [Public API is every extension surface](../../../../coding-guidelines/core/backward-compatibility.md#public-api-is-every-extension-surface). Example: a new plugin management interface had a single internal consumer; new customer route extensions duplicated what route decoration already covered; the document v2 rework added speculative events that reviewers preferred to add with their first real use.
- **DIX-005** Look for services that memoize data in a property (a cache array, a loaded config, a counter) without `ResetInterface` or the `kernel.reset` tag. In long-running workers the data leaks into the next request or message, in the cloud into the next tenant. Rule: [ADR reset class state](../../../../adr/2022-03-09-reset-class-state-during-requests.md#decision). Example: the reCAPTCHA service kept data between requests, a document type registry lacked the `kernel.reset` tag, and `PluginService` cached a property without `ResetInterface`.
- **DIX-006** Look at the payload of a new notification event: it should carry ids and the context consumers need (version id, type name), not whole entities, and not extend Symfony `Event` unless stopping propagation is intended; an extension event instead carries the input objects listeners may change. Entities freeze the entity shape into the event and block asynchronous handling. Rule: [Mediator](../../../../coding-guidelines/core/extendability.md#mediator), [Extension events](../../../../coding-guidelines/core/extendability.md#extension-events). Example: `DocumentGeneratedEvent` exposed the full order entity instead of a domain payload and extended Symfony `Event` without being meant as stoppable; an available-combination event that only notifies handed out a query builder.
- **DIX-007** Look for a new static helper class that does work a DI service already does or should do (rounding, config, formatting with dependencies), called from a service. Plugins cannot decorate or replace it, and tests cannot stub it. Inject the service through the constructor. Rule: [PHP code, Structure](../../shopware-php-code/SKILL.md#structure); guideline paragraph pending. Example: the document template renderer became a static helper although it needs dependencies such as the business timezone; reviewers asked for a service.

## Do not flag

### CI covers

- `DecorationPatternRule` and `AbstractClassUsageRule` (missing `getDecorated()`, extra public methods, subscribers as decorators, concrete type hints for decoratable services), `PublicServiceDecoratorRule`, `NoRouteOverrideInDecoratorsRule`, `TaggedServiceContractRule`, `ExtensionRule`, `StoreApiRouteExtensionRule`.
- `NoNativeTimeFunctionRule` and `NoNativeTimeClassRule`: native time reads instead of an injected `ClockInterface`.

### Legitimate patterns

- Static helpers that are pure functions without dependencies (string or array utilities). Static helper versus service is contested among reviewers; flag DIX-007 only when a service for the same job exists or the helper needs dependencies.
- `@internal` services and seams, test doubles, and new events on code that is not called per request.
- Changes to existing points (renamed event properties, reworked flows that bypass listeners or decorators), new Store API routes (`Abstract*Route` versus extension event), `Request` in a payload and seams plugins cannot extend belong to the bc-extension-points guide (BCEXT-001, -005, -006, -009, -010).

## Severity

- `major`, `requires_human: true`: a new public extension point of the wrong shape or in a hot path. It becomes a contract for years; a person decides whether it is wanted.
- `minor`, `requires_human: true`: a new public extension point without a named consumer (DIX-004). The rule rests on a proposed policy, so a person decides.
- `major`: mutable state without `ResetInterface`, or a decorator base method that is abstract. Data leaks between requests, or every plugin decorator breaks.
- `minor`: an event payload with entities, or a static helper where a service exists.

## Retired

(none yet)
