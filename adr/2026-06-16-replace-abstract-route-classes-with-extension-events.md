---
title: Replace abstract route classes with the extension event system
date: 2026-06-16
area: framework
tags: [store-api, routing, extensions, decorator, plugin]
status: proposed
---

## Context

Every store-api route ships as a pair: an abstract base class such as `AbstractProductDetailRoute` and a concrete implementation such as `ProductDetailRoute extends AbstractProductDetailRoute`.
The abstract class exists for one reason, to let plugins replace or wrap the route through the decorator pattern.
The base declares the public method and an `abstract public function getDecorated()`, the concrete class throws `DecorationPatternException` from `getDecorated()`, and a plugin that wants to hook in registers a service decoration.

This works, but it costs a lot.
For each route we maintain a second class that holds nothing but signatures.
A plugin author who only wants to read the result, add a header, or short-circuit one call has to write a full class:

```php
class MyProductSearchRoute extends AbstractProductSearchRoute
{
    public function __construct(private readonly AbstractProductSearchRoute $decorated) {}

    public function getDecorated(): AbstractProductSearchRoute
    {
        return $this->decorated;
    }

    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): ProductSearchRouteResponse
    {
        // the only interesting line is somewhere in here
        return $this->getDecorated()->load($request, $context, $criteria);
    }
}
```

plus a service definition with a `decorates` tag.
The decorator also gives a single hook: you override the whole method.
There is no separate point for "before", "after", or "the call threw".
If two plugins decorate the same route, the behavior depends on decoration priority, which is hard to reason about and easy to get wrong.

We already have a different mechanism for exactly this kind of extension.
`Shopware\Core\Framework\Extensions` provides an `Extension` base class and an `ExtensionDispatcher` that wraps a piece of logic with `.pre`, `.post`, and `.error` events.
It was introduced in [Transition to an Event-Based Extension System](./2024-06-18-extended-event-system.md) and is in use in several places already (cart, document rendering, sitemap file, CMS slot resolving, media thumbnails, product listing).
[New document generation extension points](./2026-03-19-new-document-generation-extension-points.md) is a recent case of swapping a decorated service for these events.
PR [#17296](https://github.com/shopware/shopware/pull/17296) applies the same mechanism to the customer account routes and shows what the migration looks like in practice.
This ADR weighs keeping the abstract-route decorator pattern against moving route extension onto the existing event system, and records the decision to migrate.

## How the decorator pattern works today

The abstract base is the extension contract.
A plugin subclasses it, receives the decorated route by constructor injection, and delegates.

**Problems:**

* Decorating a route can re-declare the `#[Route]` attribute, and that is dangerous.
A copied attribute can silently drop or change a route default such as `_routeScope` or a "login required" flag, which alters or disables the route without any error.
* A wrong route attribute breaks the decoration chain.
The attribute that "wins" decides which service id Symfony resolves the controller from.
In a chain like Plugin A -> Plugin B -> Core, if Plugin B redefines the core route attribute, Symfony fetches Plugin B's controller directly and Plugin A's decoration never runs.
This has already caused subtle failures in real multi-plugin setups, where a decorator was silently ignored and took hours to track down.
* The danger is already codified.
`NoRouteOverrideInDecoratorsRule` forbids decorators from defining route attributes at all: "only the core route should define the @Route attribute".
That means safe decoration has to target the concrete implementation rather than the abstract base, which undercuts the abstract class as the intended extension point.
* There is one hook point per method.
Reading the result, mutating arguments, and recovering from an exception all collapse into "reimplement the method and remember to call the parent".
* Each route carries a second class (the abstract base) that only restates method signatures, so the signature lives in two places and has to be kept in sync.
The smallest possible extension is still a full class plus a service decoration, and `getDecorated()` is dead weight on the concrete class that exists only to throw `DecorationPatternException`.

## The extension event system

`ExtensionDispatcher::publish()` wraps the route body.
The route keeps its public method and `#[Route]` attribute, moves the body into a private method, and hands that body to the dispatcher together with an `Extension` instance carrying the input parameters.
`ProductListingLoader` already works this way (it publishes `ResolveListingExtension` around its load logic), so the route case is the same pattern applied to controllers.

```php
#[Route(path: '/store-api/product/{productId}', name: 'store-api.product.detail', methods: ['GET', 'POST'])]
public function load(string $productId, Request $request, SalesChannelContext $context, Criteria $criteria): ProductDetailRouteResponse
{
    return $this->extensions->publish(
        name: ProductDetailRouteExtension::NAME,
        extension: new ProductDetailRouteExtension($productId, $request, $context, $criteria),
        function: $this->_load(...),
    );
}

private function _load(string $productId, Request $request, SalesChannelContext $context, Criteria $criteria): ProductDetailRouteResponse
{
    // the body that used to live in load()
}
```

The dispatcher runs the body between two events and adds error handling, following `ExtensionDispatcher::publish()`:

```
dispatch <name>.pre with the extension
if propagation was stopped:
    skip the body, use the result a subscriber already set
else:
    try:
        result = body(...extension public params)
    catch Throwable e:
        extension.exception = e
        dispatch <name>.error
        if no subscriber set a result: rethrow e
dispatch <name>.post
return extension.result
```

The `Extension` subclass is a data carrier.
Its public, readonly properties are the parameters the body receives, and `NAME` identifies the event.

```php
/**
 * @extends Extension<ProductDetailRouteResponse>
 */
#[Package('inventory')]
final class ProductDetailRouteExtension extends Extension
{
    public const NAME = 'product-detail-route.load';

    public function __construct(
        public readonly string $productId,
        public readonly Request $request,
        public readonly SalesChannelContext $context,
        public readonly Criteria $criteria,
    ) {}
}
```

A plugin extends the route by subscribing, with no new class hierarchy and no service decoration:

```php
class AddTrackingHeaderSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ProductDetailRouteExtension::onPre() => 'beforeLoad',
            ProductDetailRouteExtension::onPost() => 'afterLoad',
        ];
    }

    public function beforeLoad(ProductDetailRouteExtension $extension): void
    {
        // read or mutate $extension->criteria, or set $extension->result and
        // $extension->stopPropagation() to replace the route entirely
    }

    public function afterLoad(ProductDetailRouteExtension $extension): void
    {
        // inspect or change $extension->result after the route ran
    }
}
```

## Comparing the two approaches

The decorator pattern gives full control: a decorator sits in the call chain and can do anything around the inner call, including changing the injected dependencies it was built with.
That control comes with a real hazard.
While decorating a route a plugin can re-declare the `#[Route]` attribute, and a wrong attribute either changes the route's behavior or breaks the decoration chain so a downstream decorator never runs.
`NoRouteOverrideInDecoratorsRule` exists precisely because this goes wrong in practice, and it pushes safe decoration onto the concrete class rather than the abstract base the pattern was built around.
On top of that, every extension is a class, the contract is duplicated in the abstract base, and the only hook is "replace the method".

The event system trades that raw control for less surface and more precise hooks, and it leaves the route definition alone.
The `#[Route]` attribute stays on the core class, so none of the route-resolution pitfalls apply.
A subscriber cannot swap the route's constructor dependencies, but for the cases plugins actually use (read the result, adjust the input, replace the output, recover from an error) it is a single method on a subscriber.
The three events separate concerns that the decorator merges, and `stopPropagation()` makes "replace the route" explicit instead of "forget to call the parent".
A single subscriber can also bundle several routes' extension points together instead of shipping one decorator class per route.

| Concern                     | Decorator + abstract base                     | Extension events                               |
|-----------------------------|-----------------------------------------------|------------------------------------------------|
| Route definition safety     | Re-declares `#[Route]`, can break resolution  | Route attribute stays on the core class        |
| Minimal extension           | New class + service decoration                | One subscriber method                          |
| Per-route maintenance       | Abstract base mirrors every signature         | One `Extension` subclass holding the params    |
| Hook points                 | Whole method, one override                    | `.pre`, `.post`, `.error`                      |
| Replace the operation       | Reimplement, optionally skip `getDecorated()` | Set `result` + `stopPropagation()`             |
| Error recovery              | Wrap the call in try/catch yourself           | Subscribe to `.error`, set a fallback `result` |
| Composition between plugins | Decoration priority, implicit chain           | Listener priority, same as other events        |
| Access to route internals   | Full (can wrap dependencies)                  | Limited to the published parameters            |

The one capability we give up is wrapping the route's own dependencies.
In practice plugins decorate routes to influence input and output, not to rebuild the route's collaborators, so the loss is small against the route-safety problems and boilerplate it removes.

## Decision

We will extend store-api routes through the extension event system and deprecate the abstract route classes together with the decorator-based extension path.

Concretely:

* Each store-api route keeps its public method and `#[Route]` attribute and moves its body into a private method passed to `ExtensionDispatcher::publish()`.
* Each route gets an `Extension` subclass carrying its parameters as public readonly properties and a stable `NAME`.
* The abstract base classes (`AbstractProductDetailRoute`, `AbstractProductSearchRoute`, and the rest) and the `getDecorated()` methods are deprecated, not removed in the same major.
They keep working while plugins migrate.
* Where the core itself decorates a route to add behavior (for example `ResolvedCriteriaProductSearchRoute`, which wraps `load()` to prepare the criteria and dispatch search events),
we move that behavior into a subscriber on the new events or fold it into the route body, and drop the internal decorator.

We roll this out behind the normal deprecation process rather than in one step,
because the abstract classes are part of the public API and plugins depend on them.

## Extendability

The events cover the cases plugins decorate routes for today:

* **Change the input before the route runs.**
Subscribe to `.pre` and mutate the published parameters, for example add a filter to the `Criteria` or rewrite a request value.
* **Replace the route.**
On `.pre`, set `$extension->result` and call `stopPropagation()`.
The body never runs.
This is the event-system equivalent of a decorator that does not call `getDecorated()`.
* **Post-process the result.**
Subscribe to `.post` and read or rewrite `$extension->result`, for example enrich a response or add a header.
* **Recover from failure.**
Subscribe to `.error`, inspect `$extension->exception`, and set a fallback `$extension->result` to swallow it.
Leaving the result unset rethrows the original exception.

Business cases this serves: a B2B plugin that injects customer-specific filters into product and listing routes on `.pre`,
a search plugin that replaces the product search route with an external engine via `stopPropagation()`,
an analytics plugin that records every store-api response on `.post`,
and a resilience plugin that returns a cached fallback on `.error` when a downstream call fails.

## Consequences

### For the platform

* Two classes per route collapse toward one.
The abstract base disappears after the deprecation window, and the concrete route loses `getDecorated()` and `DecorationPatternException`.
* Each route gains one `Extension` subclass and a private body method.
The public signature and routing attribute do not change, so the HTTP contract is untouched.
* Internal decorators that exist only to wrap a route (such as `ResolvedCriteriaProductSearchRoute`) are refactored into subscribers or merged into the route body, which removes a layer from the call chain.
* During the deprecation window both mechanisms run.
A route can be decorated and published through the dispatcher at the same time, so we carry both paths until the abstract classes are removed.
* Every migrated route needs `.pre`, `.post`, and `.error` coverage in tests, including the `stopPropagation()` and `.error` fallback paths.

### For third-party developers

* The abstract route classes and `getDecorated()` are deprecated.
Code that extends an `Abstract*Route` keeps working until the classes are removed in a later major, with a deprecation notice in the meantime.
* New extension code should subscribe to the route's `Extension` events instead of decorating.
A decoration that only reads input or output, or replaces the result, becomes a subscriber method without a service decoration.
* Decorators that genuinely need to wrap the route's own dependencies have no direct replacement.
These are rare for routes, and such cases should be raised so we can decide whether the route needs a dedicated extension point.
* Ordering between plugins moves from decoration priority to event listener priority.
Plugins that relied on a specific decoration order need to set listener priorities instead.

## Considered alternatives

1. **Keep the decorator pattern and abstract classes.**
No migration cost and full wrapping power, but the boilerplate and the single coarse hook stay, and the route-redefinition hazard that `NoRouteOverrideInDecoratorsRule` guards against stays with it.
We would also keep maintaining two parallel extension systems in the codebase.
Rejected because we already committed to the extension event system elsewhere and want one model for route extension.

2. **Generate the abstract base classes to cut maintenance.**
Removes the handwritten duplication but keeps the decorator's coarse hook, the service-decoration boilerplate for plugins, and the implicit composition order.
It treats a symptom and leaves the extension model unchanged.
Rejected in favor of moving to the events.

3. **Run both systems permanently as equal options.**
Publish through the dispatcher and keep the abstract classes for those who prefer decoration.
This is the transition state during deprecation, but as a permanent choice it doubles the surface plugin authors have to understand and the core has to maintain.
Rejected as an end state, accepted only for the deprecation window.

## Open points

Chained behavior carries over from decoration and needs an explicit interaction model.
Several extensions can act on the same operation, and one that rewrites `$extension->result` on `.post` has no signal that another already changed it.
We are not adding conflict detection here, because existing requirements already depend on this kind of layering and the mechanism has to keep allowing it.
What is open is how we document and order the interaction: whether routes that expect layered subscribers should state the contract for reading versus replacing the result, and how listener priorities are meant to be used across plugins.
This should be settled before the events are recommended as the default extension path.
