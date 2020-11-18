[titleEn]: <>(Http cache)
[hash]: <>(article:storefront_http_cache)

## How to configure the http cache
The HTTP cache configuration takes place completely in the .env file. The following configurations are available here:

| Name                           | Description             |
| ------------------------------ | ----------------------- |
| `SHOPWARE_HTTP_CACHE_ENABLED`  | Enables the http cache  |
| `SHOPWARE_HTTP_DEFAULT_TTL`    | Defines the default cache time |

## How to trigger the http cache warmer
To warm up the HTTP cache you can simply use the console command `http:cache:warm:up`. This command sends a message to the message queue for each sales channel domain to warm it up as fast as possible. It is important that queue workers are started according to our [Guide](./../../20-developer-guide/80-core/10-message-queue.md).

## How to define a cacheable route
To cache a route you have to add the annotation `\Shopware\Storefront\Framework\Cache\Annotation\HttpCache` in the php docs of the controller action:
```php
<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Cache\Annotation\HttpCache;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class NavigationController extends StorefrontController
{
    /**
     * @HttpCache()
     * @Route("/", name="frontend.home.page", options={"seo"="true"}, methods={"GET"})
     */
    public function home(Request $request, SalesChannelContext $context): ?Response
    {
        $page = $this->navigationPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/storefront/page/content/index.html.twig', ['page' => $page]);
    }
}
```

## How to write a http cache warmer extension
The http cache warmer can be extended by further routes, which should be considered in the warm up. The routes can be registered via the DI container tag `<tag name="http_cache.route_warmer" />`.
The following example shows a route warmer for the product detail pages:
```php
<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\CacheWarmer\Product;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Storefront\Framework\Cache\CacheWarmer\CacheRouteWarmer;
use Shopware\Storefront\Framework\Cache\CacheWarmer\WarmUpMessage;

class ProductRouteWarmer implements CacheRouteWarmer
{
    /**
     * @var IteratorFactory
     */
    private $iteratorFactory;

    /**
     * @var ProductDefinition
     */
    private $definition;

    public function __construct(IteratorFactory $iteratorFactory, ProductDefinition $definition)
    {
        $this->iteratorFactory = $iteratorFactory;
        $this->definition = $definition;
    }

    public function createMessage(SalesChannelDomainEntity $domain, ?array $offset): ?WarmUpMessage
    {
        $iterator = $this->iteratorFactory->createIterator($this->definition, $offset);
        $iterator->getQuery()->setMaxResults(10);

        $ids = $iterator->fetch();
        if (empty($ids)) {
            return null;
        }

        $ids = array_map(function ($id) {
            return ['productId' => $id];
        }, $ids);

        return new WarmUpMessage('frontend.detail.page', $ids, $iterator->getOffset());
    }
}
```

The `createMessage` function is responsible for creating a message for the queue for the defined offset. This is quite easy to do with the `IteratorFactory` class.
If the function does not return a message, it means that there are no more routes to warm up. 

## Cache state system
When a certain status is reached in the system, certain routes can no longer be cached. Some routes behave differently based on the state of the system, f.e. when a customer is logged in or when products are in the cart.
These states are automatically recognized and set as a cookie in the response. If a new request is received, these states can be checked to pass the request to the kernel instead of responding with a cached response.
Shopware sets the following states in the cookie:

| Name           | Description             |
| ---------------| ----------------------- |
| `logged-in`    | If the customer logged in |
| `cart-filled`  | If the customer has items in cart |

These states can be defined in the `@HttpCache` annotation:
```php
<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Cache\Annotation\HttpCache;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class NavigationController extends StorefrontController
{
    /**
     * @HttpCache(states={"cart-filled", "logged-in"})
     * @Route("/", name="frontend.home.page", options={"seo"="true"}, methods={"GET"})
     */
    public function home(Request $request, SalesChannelContext $context): ?Response
    {
        $page = $this->navigationPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/storefront/page/content/index.html.twig', ['page' => $page]);
    }
}
```

## Cache invalidation system
The cache invalidation is realized like the entity cache via tags. For this purpose, the `\Shopware\Storefront\Framework\Cache\CacheStore` reacts to the data in the response.
All entities that have been loaded into the template will be considered for the cache invalidation. The cache is then invalidated via `\Shopware\Core\Framework\Adapter\Cache\CacheClearer::invalidateTags`.

## How to change the cache storage
The standard shopware http cache can be exchanged or reconfigured in several ways. The standard cache comes with an `adapter.filesystem`. The configuration can be found in the `platform/src/Core/Framework/Resources/config/packages/framework.yaml` file.
```yaml
framework:
    cache:
        pools:
            cache.http:
                adapter: cache.adapter.filesystem
                tags: true
```

This is a Symfony cache pool configuration and therefore supports all adapters from Symfony: https://symfony.com/doc/current/cache.html#configuring-cache-with-frameworkbundle
However, the http cache can also be completely replaced. As a store for the cache, the service `\Shopware\Storefront\Framework\Cache\CacheStore` is fetched from the DI container during kernel boot and set as a store in the `\Symfony\Component\HttpKernel\HttpCache\HttpCache`.
Here any other service can be used which implements the Symfony `StoreInterface`:

```php
<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpCache\StoreInterface;

class MyStore implements StoreInterface
{
    public function lookup(Request $request)
    {
    }

    public function write(Request $request, Response $response)
    {
    }

    public function invalidate(Request $request)
    {
    }

    public function lock(Request $request)
    {
    }

    public function unlock(Request $request)
    {
    }

    public function isLocked(Request $request)
    {
    }

    public function purge($url)
    {
    }

    public function cleanup()
    {
    }
}
```
