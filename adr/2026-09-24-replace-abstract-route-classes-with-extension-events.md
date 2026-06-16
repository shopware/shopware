---
title: Replace abstract route classes with the extension event system
date: 2026-09-24
area: framework
tags: [store-api, routing, extensions, decorator, plugin]
status: proposed
---

## Context

Store-api routes use an abstract base class as their public extension contract.
Plugins subclass it, inject the decorated route, implement `getDecorated()`, and register a service decoration to wrap a route method.

This pattern has three drawbacks:

* **Boilerplate and signature coupling.**
  Abstract classes duplicate route signatures, and even a small plugin customization needs a decorator class.
  Adding parameters must preserve compatibility with subclasses.
* **Route declaration hazards.**
  Copying a `#[Route]` attribute into a decorator can change route defaults or controller resolution, bypassing other decorators.
  `NoRouteOverrideInDecoratorsRule` forbids these overrides.
* **Coarse hooks.**
  Input changes, result processing, and error handling all require wrapping the whole method and managing delegation.

Shopware already provides `Extension` and `ExtensionDispatcher` for this purpose, as defined in [Transition to an Event-Based Extension System](./2024-06-18-extended-event-system.md).
They are used in areas including cart processing, document rendering, and product listing.

## Decision

Use the existing extension event system as the preferred public extension point for store-api routes.

Each route keeps its public method and `#[Route]` attribute and passes its body, extracted into a private method, to `ExtensionDispatcher::publish()`.
A dedicated `Extension` subclass carries the input parameters as public readonly properties and declares a stable `NAME`.
Its constructor is `@internal` and owned by Shopware; its properties are public API.

Plugins subscribe to the following hooks:

* **`.pre`:**
  Adjust mutable input objects such as `Criteria` or `Request`.
  To replace the operation, set `$extension->result` and call `stopPropagation()`, skipping the route body.
* **`.post`:**
  Inspect or change `$extension->result`.
* **`.error`:**
  Inspect `$extension->exception` and provide a fallback result.
  Without a result, the original exception is rethrown.

## Advantages

* The event is the extension contract, allowing route implementations to become `@internal` once their existing public contracts have been retired.
* Additional route parameters and event properties do not change listener signatures.
  Existing event contracts and retained abstract route signatures must still follow backward-compatibility rules.
* No route declaration copying hazards like mentioned above.
* One listener class can implement a feature across multiple related route events.

## Consequences

* Introduce events for all store-api routes step by step.
* Keep abstract route classes and `getDecorated()` supported for now.
  Deprecate a route's abstract class and decorator-based extension path only when the route is being adjusted anyway **and** a breaking change is necessary.
  Follow the normal backward-compatibility process for deprecation and removal.
  Adding events alone does not trigger either.
  There is no blanket schedule.
* Both mechanisms coexist while the abstract contract remains supported.
  Plugin extensions should use events where available as soon as possible.
* When adjusting a route, core decorators such as `ResolvedCriteriaProductSearchRoute` can become subscribers or be merged into the route body, subject to backward compatibility.
* Migrated routes need tests for `.pre`, `.post`, and `.error`, including `stopPropagation()` and error fallback behavior.
* Plugins migrating from decoration use listener priorities to control ordering.

## Considered alternatives

* **Keep decoration as the primary extension model.**
  Avoids migration work but retains the drawbacks above and diverges from Shopware's event-based direction.
* **Recommend both models permanently.**
  Preserves choice but doubles the extension surface to understand and maintain.
  Coexistence supports compatibility; it is not the intended long-term model.
