---
title: Replace abstract route classes with the extension event system
date: 2026-09-24
area: framework
tags: [store-api, routing, extensions, decorator, plugin]
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

### Example of a new route class

#### Store-API route class

```php
<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\SalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\System\Country\Extension\ActiveCountryRouteExtension;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Package('fundamentals@discovery')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class ActiveCountryRoute
{
    /**
     * @param SalesChannelRepository<CountryCollection> $countryRepository
     */
    public function __construct(
        private readonly SalesChannelRepository $countryRepository,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    #[Route(
        path: '/store-api/active-country',
        name: 'store-api.active-country',
        methods: ['GET', 'POST'],
        defaults: [PlatformRequest::ATTRIBUTE_ENTITY => CountryDefinition::ENTITY_NAME],
    )]
    public function load(Criteria $criteria, SalesChannelContext $context): CountryRouteResponse
    {
        return $this->extensions->publish(
            name: ActiveCountryRouteExtension::NAME,
            extension: new ActiveCountryRouteExtension($criteria, $context),
            function: $this->_load(...),
        );
    }

    private function _load(Criteria $criteria, SalesChannelContext $context): CountryRouteResponse
    {
        $criteria->addFilter(new EqualsFilter('active', true));

        return new CountryRouteResponse($this->countryRepository->search($criteria, $context));
    }
}
```

#### Extension event class

```php
<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Extension;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\SalesChannel\CountryRouteResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @public
 *
 * @extends Extension<CountryRouteResponse>
 */
#[Package('fundamentals@discovery')]
final class ActiveCountryRouteExtension extends Extension
{
    public const NAME = 'active-country-route.load';

    /**
     * @internal Shopware owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly Criteria $criteria,
        public readonly SalesChannelContext $context,
    ) {
    }
}
```

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
  New routes must use extension events from the start without introducing an abstract route class.
* Add a custom PHPStan rule to enforce the pattern for new routes, with explicit exceptions for existing routes during migration.
* Keep abstract route classes and `getDecorated()` supported for now.
  Deprecate a route's abstract class and decorator-based extension path only when the route is being adjusted anyway **and** a breaking change is necessary.
  Follow the normal backward-compatibility process for deprecation and removal.
  Introducing new events does not justify a deprecation, and there is no global timeline for phasing these patterns out.
* When adjusting a route, core decorators such as `ResolvedCriteriaProductSearchRoute` can become subscribers or be merged into the route body, subject to backward compatibility.
* Route tests must verify that the correct extension name and object, including its input parameters, are dispatched.
* Update the developer guides for [adding Store API routes](https://developer.shopware.com/docs/guides/plugins/plugins/framework/store-api/add-store-api-route.html) and [overriding existing routes](https://developer.shopware.com/docs/guides/plugins/plugins/framework/store-api/override-existing-route.html), which currently teach the decorator pattern.
  Document event-based extension and migration, retaining decoration guidance for routes that do not yet expose events.
* Plugins migrating from decoration use listener priorities to control ordering.

### Plugin migration

Both mechanisms coexist while the abstract contract remains supported.
Plugin extensions should use events where available as soon as possible.

This plugin limits product search to products with free shipping.
Before migration, it wraps `ProductSearchRoute`:

```php
<?php declare(strict_types=1);

namespace Acme\FreeShipping;

use Shopware\Core\Content\Product\SalesChannel\Search\AbstractProductSearchRoute;
use Shopware\Core\Content\Product\SalesChannel\Search\ProductSearchRouteResponse;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

final class FreeShippingSearchRoute extends AbstractProductSearchRoute
{
    public function __construct(private readonly AbstractProductSearchRoute $decorated)
    {
    }

    public function getDecorated(): AbstractProductSearchRoute
    {
        return $this->decorated;
    }

    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): ProductSearchRouteResponse
    {
        $criteria->addFilter(new EqualsFilter('product.shippingFree', true));

        return $this->decorated->load($request, $context, $criteria);
    }
}
```

After migration, the same filter is applied by a listener on the `.pre` event:

```php
<?php declare(strict_types=1);

namespace Acme\FreeShipping;

use Shopware\Core\Content\Product\Extension\ProductSearchRouteExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class FreeShippingSearchSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [ProductSearchRouteExtension::onPre() => 'filter'];
    }

    public function filter(ProductSearchRouteExtension $extension): void
    {
        $extension->criteria->addFilter(new EqualsFilter('product.shippingFree', true));
    }
}
```

## Considered alternatives

* **Keep decoration as the primary extension model.**
  Avoids migration work but retains the drawbacks above and diverges from Shopware's event-based direction.
* **Recommend both models permanently.**
  Preserves choice but doubles the extension surface to understand and maintain.
  Coexistence supports compatibility; it is not the intended long-term model.
