# 6.5.8.0
## Cache rework preparation
With 6.6 we are marking a lot of HTTP Cache and Reverse Proxy classes as @internal and move them to the core. 
We are preparing a bigger cache rework in the next releases. The cache rework will be done within the v6.6 version lane and and will be released with 6.7.0 major version. 
The cache rework will be a breaking change and will be announced in the changelog of 6.7.0. We will provide a migration guide for the cache rework, so that you can prepare your project for the cache rework.

You can find more details about the cache rework in the [shopware/shopware discussions](https://github.com/shopware/shopware/discussions/3299)

Since the cache is a critical component for systems, we have taken the liberty of marking almost all classes as @internal for the time being. However, we have left the important events and interfaces public so that you can prepare your systems for the changes now.
Even though there were a lot of deprecations in this release, 99% of them involved moving the classes to the core domain.

But there is one big change that affects each project and nearly all repositories outside which are using phpstan. 

### Kernel bootstrapping
We had to refactor the Kernel bootstrapping and the Kernel itself. 
When you forked our production template, or you boot the kernel somewhere by your own, you have to change the bootstrapping as follows:

```php

#### Before #####

$kernel = new Kernel(
    environment: $appEnv, 
    debug: $debug, 
    pluginLoader: $pluginLoader
);

#### After #####

$kernel = KernelFactory::create(
    environment: $appEnv,
    debug: $debug,
    classLoader: $classLoader,
    pluginLoader: $pluginLoader
);


### In case of static code analysis

KernelFactory::$kernelClass = StaticAnalyzeKernel::class;

/** @var StaticAnalyzeKernel $kernel */
$kernel = KernelFactory::create(
    environment: 'phpstan',
    debug: true,
    classLoader: $this->getClassLoader(),
    pluginLoader: $pluginLoader
);

```

### Session access in phpunit tests
The way how you can access the session in unit test has changed.
The session is no more accessible via the request/response.
You have to use the `session.factory` service to access it or use the `SessionTestBehaviour` for a shortcut

```php
##### Before

$this->request(....);

$session = $this->getBrowser()->getRequest()->getSession();

##### After

use SessionTestBehaviour;

$this->request(....);

// shortcut via trait 
$this->getSession();

// code behind the shortcut
$this->getContainer()->get('session.factory')->getSession();

```

### Manipulate the http cache
Since we are moving the cache to the core, you have to change the way you can manipulate the http cache. 

1) If you decorated or replaced the `src/Storefront/Framework/Cache/HttpCacheKeyGenerator.php`, this will be no more possible in the upcoming release. You should use the http cache events
2) You used one of the http cache events --> They will be moved to the core, so you have to adapt the namespace+name of the event class. The signature is also not 100% the same, so please check the new event classes (public properties, etc.)

```php

#### Before

<?php

namespace Foo;

use Shopware\Storefront\Framework\Cache\Event\HttpCacheGenerateKeyEvent;
use Shopware\Storefront\Framework\Cache\Event\HttpCacheHitEvent;
use Shopware\Storefront\Framework\Cache\Event\HttpCacheItemWrittenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class Subscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            HttpCacheHitEvent::class => 'onHit',
            HttpCacheGenerateKeyEvent::class => 'onKey',
            HttpCacheItemWrittenEvent::class => 'onWrite',
        ];
    }
}

#### After
<?php

namespace Foo;

use Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheHitEvent;
use Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheKeyEvent;
use Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheStoreEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class Subscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            HttpCacheHitEvent::class => 'onHit',
            HttpCacheKeyEvent::class => 'onKey',
            HttpCacheStoreEvent::class => 'onWrite',
        ];
    }
}



```

### Own reverse proxy gateway
If you implement an own reverse proxy gateway, you have to change the namespace of the gateway and the event.

```php
#### Before

class RedisReverseProxyGateway extends \Shopware\Storefront\Framework\Cache\ReverseProxy\AbstractReverseProxyGateway
{
    // ...
}


#### After

class RedisReverseProxyGateway extends \Shopware\Core\Framework\Adapter\Cache\ReverseProxy\AbstractReverseProxyGateway
{
    // ...
}
```

### Http cache warmer

We deprecated all Http cache warmer, because they will be not usable with the new http kernel anymore. 
They are also not suitable for the new cache rework or for systems which have a reverse proxy or a load balancer in front of the shopware system.
Therefore, we marked them as deprecated and will remove them in the next major version.
You should use instead a real website crawler to warmup your desired sites, which is much more suitable and realistic for your system.

If you are relying on the `sales_channel.analytics` association, please associate the definition directly with the criteria because we will remove autoload from version 6.6.0.0.

# 6.5.7.0
## New context state scoping feature
You can now simply adding a context state temporarily for an internal process without saving the previous scope and restore it:

```php
<?php

namespace Examples;

use Shopware\Core\Framework\Context;

class Before
{
    public function foo(Context $context) 
    {
        $before = $context->getStates();

        $context->addState('state-1', 'state-2');
                
        // do some stuff or call some services which changed the scope

        $context->removeState('state-1');
        
        $context->removeState('state-2');
    }
}

class After
{
    public function foo(Context $context) 
    {
        $context->state(function (Context $context) {
            // do some stuff or call some services which changed the scope
        }, 'state-1', 'state-2');
    }
}
```

## Stored media path
Within the v6.5 lane, the media path handling changed in a way, where we store the path in the database instead of generating it always on-demand. 
They will be generated, when the media is uploaded. The path can also be provided via api to handle external file uploads.

We also removed the dependency to the entity layer and allow much faster and simpler access to the media path via location structs and a new url generator.
Due to this change, the usage of the `UrlGeneratorInterface` changed. The generator is deprecated and will be removed with v6.6.0. We implemented a new generator `MediaUrlGenerator` which can be used instead.

### Generating a media or thumbnail url

```php
<?php 

namespace Examples;

use Shopware\Core\Content\Media\Core\Application\AbstractMediaUrlGenerator;use Shopware\Core\Content\Media\Core\Params\UrlParams;use Shopware\Core\Content\Media\MediaCollection;use Shopware\Core\Content\Media\MediaEntity;use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;

class BeforeChange
{
    private UrlGeneratorInterface $urlGenerator;
    
    public function foo(MediaEntity $media) 
    {
        $relative = $this->urlGenerator->getRelativeMediaUrl($media);
        
        $absolute = $this->urlGenerator->getAbsoluteMediaUrl($media);
    }
    
    public function bar(MediaThumbnailEntity $thumbnail) 
    {
        $relative = $this->urlGenerator->getRelativeThumbnailUrl($thumbnail);
        
        $absolute = $this->urlGenerator->getAbsoluteThumbnailUrl($thumbnail);
    }
}

class AfterChange
{
    private AbstractMediaUrlGenerator $generator;
    
    public function foo(MediaEntity $media) 
    {
        $relative = $media->getPath();

        $urls = $this->generator->generate([UrlParams::fromMedia($media)]);
        
        $absolute = $urls[0];
    }
    
    public function bar(MediaThumbnailEntity $thumbnail) 
    {
        // relative is directly stored at the entity
        $relative = $thumbnail->getPath();

        // path generation is no more entity related, you could also use partial entity loading and you can also call it in batch, see below
        $urls = $this->generator->generate([UrlParams::fromMedia($media)]);
        
        $absolute = $urls[0];
    }
    
    public function batch(MediaCollection $collection) 
    {
        $params = [];
        
        foreach ($collection as $media) {
            $params[$media->getId()] = UrlParams::fromMedia($media);
            
            foreach ($media->getThumbnails() as $thumbnail) {
                $params[$thumbnail->getId()] = UrlParams::fromThumbnail($thumbnail);
            }
        }
        
        $urls = $this->generator->generate($paths);

        // urls is a flat list with {id} => {url} for media and also for thumbnails        
    }
}

class ForwardCompatible
{
    // to have it forward compatible, you can use the Feature::isActive('v6.6.0.0') function
    public function foo(MediaEntity $entity) 
    {
        // we provide an entity loaded subscriber, which assigns the url of
        // the UrlGeneratorInterface::getRelativeMediaUrl to the path property till 6.6
        // so that you always have the relative url in the MediaEntity::path proprerty 
        $path = $entity->getPath();
        
        if (Feature::isActive('v6.6.0.0')) {
            // new generator call
        } else {
            // old generator call
        }
    }
}
```

### Path strategies
Beside the url generator change, we also had to change the media path strategy. The strategies are no longer working with a `MediaEntity`. They are now working with a `MediaFile` object. This object is a simple struct, which contains the path and the updated at timestamp. The path is the same as the one stored in the database. The updated at timestamp is the timestamp, when the path was generated. This is important for the cache invalidation. The `MediaFile` object is also used for the thumbnail generation. The thumbnail generation is now also working with a `MediaLocation` object instead.

As foundation, we use `\Shopware\Core\Content\Media\Core\Application\AbstractMediaPathStrategy` as base class and dependency injection service id:

```php
<?php

namespace Examples;

class Before extends AbstractPathNameStrategy
{
    public function getName(): string
    {
        return 'filename';
    }

    public function generatePathHash(MediaEntity $media, ?MediaThumbnailEntity $thumbnail = null): ?string
    {
        return $this->generateMd5Path($media->getFileName());
    }
}


class After extends AbstractMediaPathStrategy
{
    public function name(): string
    {
        return 'file_name';
    }

    protected function value(MediaLocationStruct|ThumbnailLocationStruct $location): ?string
    {
        return $location instanceof ThumbnailLocationStruct ? $location->media->fileName : $location->fileName;
    }

    protected function blacklist(): array
    {
        return ['ad' => 'g0'];
    }
}
```

It is no more necessary to call the path hashing by your own. All cache busting and other logic is done in the abstract implementation. The functions are now seperated and can be reused in your implementation.
The path is generated by 4 segments:

```php
$paths[$location->id] = implode('/', \array_filter([
    $type,
    $this->md5($this->value($location)),
    $this->cacheBuster($location),
    $this->physicalFilename($location),
]));
```

### Entity dependency
If you want, you can overwrite all of this parts by your own. The strategies are now using `MediaLocationStruct`s or `ThumbnailLocationStruct`s. 
These structs are simple structs, which contains the necessary information to generate the path. We also provide a builder class to simply generate this classes based on entity identifiers:

```php
<?php

namespace Examples;

use Shopware\Core\Content\Media\Core\Application\AbstractMediaPathStrategy;use Shopware\Core\Content\Media\Core\Application\MediaLocationBuilder;

class Consumer
{
    private MediaLocationBuilder $builder;
    private AbstractMediaPathStrategy $strategy;
    
    public function foo(array $mediaIds)
    {
        $locations = $this->builder->buildLocations($mediaIds);
        
        $paths = $this->strategy->generate($locations);        
    }
}
```

If you implement your own strategy, and you require more data, you can add an event listener for the `MediaLocationEvent` or `ThumbnailLocationEvent` which allows data manipulation for the provided structs.
## Async theme compilation (@experimental)

It is now possible to trigger the compilation of the storefront css and js via the message queue instead of directly 
inside the call that changes the theme or activates/deactivates an extension.

You can change the compilation type with the system_config key `core.storefrontSettings.asyncThemeCompilation` in the 
administration (`settings -> system -> storefront`)
## Add shipping and payment method technical names
The technical name is only required in the Administration for now, but will be required in the API as well in the future (v6.7.0.0). 
It is used to identify the payment and shipping method in the API and in the administration.

To prevent issues with the upgrade to v6.7.0.0, please make sure to add a technical name to all payment and shipping methods:

**Merchants** should add a technical name to all custom created payment and shipping methods in the administration.

**Plugin developers** should add a technical name to all payment and shipping methods during plugin installation / update.

**App developers** do not need to do anything, as the technical name is automatically generated based on the app name and the payment or shipping method identifier given in the `manifest.xml`.
This includes existing app installations.
## Use new key inside app CMS elements
change in app iFrame this
```js
data.subscribe(
    'your-cms-element-name__config-element',
    yourCallback,
{ selectors: yourSelectors });
```
to
```js
const elementId = new URLSearchParams(window.location.search).get('elementId');

data.subscribe(
    // add elementId to data key for identifying the correct element config
    'your-cms-element-name__config-element' + '__' + elementId,
    yourCallback,
{ selectors: yourSelectors });
```
## Transport can be overridden on message level
If you explicitly configure a message to be transported via the `async` (default) queue, even though it implements the `LowPriorityMessageInterface` which would usually be transported via the `low_priority` queue, the transport is overridden for this specific message.

Example:
```php
<?php declare(strict_types=1);

namespace Your\Custom;

class LowPriorityMessage implements LowPriorityMessageInterface
{
}
```

```yaml
framework:
    messenger:
        routing:
            'Shopware\Core\Framework\MessageQueue\LowPriorityMessageInterface': low_priority
            'Your\Custom\LowPriorityMessage': async
```

## Configure another transport for the "low priority" queue
The transport defaults to use Doctrine. You can use the `MESSENGER_TRANSPORT_LOW_PRIORITY_DSN` environment variable to change it.

Before:
```yaml
parameters:
    messenger.default_transport_name: 'v65'
    env(MESSENGER_TRANSPORT_DSN): 'doctrine://default?auto_setup=false'
```

After:
```yaml
parameters:
    messenger.default_transport_name: 'v65'
    env(MESSENGER_TRANSPORT_DSN): 'doctrine://default?auto_setup=false'
    env(MESSENGER_TRANSPORT_LOW_PRIORITY_DSN): 'doctrine://default?auto_setup=false&queue_name=low_priority'
```

For further details on transports with different priorities, please refer to the Symfony Docs: https://symfony.com/doc/current/messenger.html#prioritized-transports

## Lower the priority for async messages
You might consider using the new `low_priority` queue if you are dispatching messages that do not need to be handled immediately.
To configure specific messages to be transported via the `low_priority` queue, you need to either adjust the routing or implement the `LowPriorityMessageInterface`:

```yaml
framework:
    messenger:
        routing:
            'Your\Custom\Message': low_priority
```

or

```php
<?php declare(strict_types=1);

namespace Your\Custom;

class Message implements LowPriorityMessageInterface
{
}
```
## LineItem payload replacement behavior

The method `\Shopware\Core\Checkout\Cart\LineItem\LineItem::replacePayload` does not do a recursive replacement of the payload anymore, but replaces the payload only on a first level.

Therefore, subarrays of the payload may reduce in items instead of being only added to.

# 6.5.6.0
## Cluster setup configuration

There is a new configuration option `shopware.deployment.cluster_setup` which is set to `false` by default. If you are using a cluster setup, you need to set this option to `true` in your `config/packages/shopware.yaml` file.
## Deprecation of CacheInvalidatorStorage

We deprecated the default delayed cache invalidation storage, as it is not ideal for multi-server usage.
Make sure you switch until 6.6 to the new RedisInvalidatorStorage.

```yaml
shopware:
    cache:
        invalidation:
            delay_options:
                storage: cache
                dsn: 'redis://localhost'
```

# 6.5.5.0
Shopware 6.5 introduces a new more flexible stock management system. Please see the [ADR](adr/2023-05-15-stock-api.md) for a more detailed description of the why & how.

It is disabled by default, but you can opt in to the new system by enabling the `STOCK_HANDLING` feature flag.

When you opt in and Shopware is your main source of truth for stock values, you might want to migrate the available_stock field to the `stock` field so that the `stock` value takes into account open orders.

You can use the following SQL:

```sql
UPDATE product SET stock = available_stock WHERE stock != available_stock
```

Bear in mind that this query might take a long time, so you could do it in a loop with a limit. See `\Shopware\Core\Migration\V6_6\Migration1691662140MigrateAvailableStock` for inspiration.

## If you have decorated `StockUpdater::update`

If you have previously decorated `\Shopware\Core\Content\Product\DataAbstractionLayer\StockUpdater` you must refactor your code. Depending on what you want to accomplish you have two options:

* You have the possibility to decorate the `\Shopware\Core\Content\Product\Stock\AbstractStockStorage::alter` method. This method is called by `\Shopware\Core\Content\Product\Stock\OrderStockSubscriber` as orders are created and transitioned through the various states. By decorating you can persist the stock deltas to a different storage. For example, an API.
* You can disable `\Shopware\Core\Content\Product\Stock\OrderStockSubscriber` entirely with the `stock.enable_stock_management` configuration setting, and implement your own subscriber to listen to order events. You can use Shopware's stock storage `\Shopware\Core\Content\Product\Stock\AbstractStockStorage`, or implement your own entirely.

## Decorating `\Shopware\Core\Content\Product\SalesChannel\Detail\AbstractAvailableCombinationLoader::load()` && `\Shopware\Core\Content\Product\SalesChannel\Detail\AvailableCombinationLoader::load()`

If you decorated `\Shopware\Core\Content\Product\SalesChannel\Detail\AvailableCombinationLoader::load()` you should instead decorate `\Shopware\Core\Content\Product\SalesChannel\Detail\AvailableCombinationLoader::loadCombinations()`. The method does the same, but the signature is slightly modified.

If you extended `\Shopware\Core\Content\Product\SalesChannel\Detail\AbstractAvailableCombinationLoader`, you should implement the new `loadCombinations` instead of `load` method.

Before:

```php
use Shopware\Core\Content\Product\SalesChannel\Detail\AbstractAvailableCombinationLoader;
use Shopware\Core\Content\Product\SalesChannel\Detail\AvailableCombinationResult;
use Shopware\Core\Framework\Context;

class AvailableCombinationLoaderDecorator extends AbstractAvailableCombinationLoader
{
    public function load(string $productId, Context $context, string $salesChannelId): AvailableCombinationResult
    {
    
    }
}
```

After:

```php
use Shopware\Core\Content\Product\SalesChannel\Detail\AbstractAvailableCombinationLoader;
use Shopware\Core\Content\Product\SalesChannel\Detail\AvailableCombinationResult;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class AvailableCombinationLoaderDecorator extends AbstractAvailableCombinationLoader
{
    public function loadCombinations(string $productId, SalesChannelContext $salesChannelContext): AvailableCombinationResult
    {
        $context = $salesChannelContext->getContext();
        $salesChannelId = $salesChannelContext->getSalesChannelId();
    }
}
```

Similarly, if you consume `\Shopware\Core\Content\Product\SalesChannel\Detail\AbstractAvailableCombinationLoader` then you will need to adjust your code, to pass in `\Shopware\Core\System\SalesChannel\SalesChannelContext`.

Before:

```php
use Shopware\Core\Content\Product\SalesChannel\Detail\AbstractAvailableCombinationLoader;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SomeService
{
     public function __construct(private AbstractAvailableCombinationLoader $availableCombinationLoader)
     {}
     
     public function __invoke(SalesChannelContext $salesChannelContext): void
     {
        $this->availableCombinationLoader->load('some-product-id', $salesChannelContext->getContext(), $salesChannelContext->getSalesChannelId());
     }
}
```

After:

```php
use Shopware\Core\Content\Product\SalesChannel\Detail\AbstractAvailableCombinationLoader;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SomeService
{
     public function __construct(private AbstractAvailableCombinationLoader $availableCombinationLoader)
     {}
     
     public function __invoke(SalesChannelContext $salesChannelContext): void
     {
        $this->availableCombinationLoader->loadCombinations('some-product-id', $salesChannelContext);
     }
}
```

## Loading stock information from a different source

If Shopware is not the source of truth for your stock data, you can decorate `\Shopware\Core\Content\Product\Stock\AbstractStockStorage` and implement the `load` method. When products are loaded in Shopware the `load` method will be invoked with the loaded product ID's. You can return a collection of `\Shopware\Core\Content\Product\Stock\StockData` objects, each representing a products stock level and configuration. This data will be merged with the Shopware stock levels and configuration from the product. Any data specified will override the product's data.

For example, you can use an API to fetch the stock data:

```php
//<plugin root>/src/Service/StockStorageDecorator.php
<?php

namespace Swag\Example\Service;

use Shopware\Core\Content\Product\Stock\AbstractStockStorage;
use Shopware\Core\Content\Product\Stock\StockData;
use Shopware\Core\Content\Product\Stock\StockDataCollection;
use Shopware\Core\Content\Product\Stock\StockLoadRequest;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class StockStorageDecorator extends AbstractStockStorage
{
    public function __construct(private AbstractStockStorage $decorated)
    {
    }

    public function getDecorated(): AbstractStockStorage
    {
        return $this->decorated;
    }

    public function load(StockLoadRequest $stockRequest, SalesChannelContext $context): StockDataCollection
    {
        $productsIds = $stockRequest->productIds;

        //use $productIds to make an API request to get stock data
        //$result would come from the api response
        $result = ['product-1' => 5, 'product-2' => 10];

        return new StockDataCollection(
            array_map(function (string $productId, int $stock) {
                return new StockData($productId, $stock, true);
            }, array_keys($result), $result)
        );
    }

    public function alter(array $changes, Context $context): void
    {
        $this->decorated->alter($changes, $context);
    }

    public function index(array $productIds, Context $context): void
    {
        $this->decorated->index($productIds, $context);
    }
}
```

```xml
<!--<plugin root>/src/Resources/config/services.xml-->
<services>
    <service id="Swag\Example\Service\StockStorageDecorator" decorates="Shopware\Core\Content\Product\Stock\StockStorage">
        <argument type="service" id="Swag\Example\Service\StockStorageDecorator.inner" />
    </service>

</services>
```

## Reading and writing the current stock level

The `product.stock` field is now a realtime representation of the product stock. When writing new extensions which need to query the stock of a product, use this field. Not the `product.availableStock` field.

Before:

```php
/** \Shopware\Core\Content\Product\ProductEntity $product */
$stock = $product->getAvailableStock();
```

After:

```php
/** \Shopware\Core\Content\Product\ProductEntity $product */
$stock = $product->getStock();
```

## Writing the current stock level

If you write to the `product.availableStock` field, you should instead write to the `product.stock` field. However, there are no plans to remove the `product.availableStock` field.

Before:

```php

$this->productRepository->update(
    [
        [
            'id' => $productId,
            'availableStock' => $newStockValue
        ]
    ],
    $context
);
```

After:

```php

$this->productRepository->update(
    [
        [
            'id' => $productId,
            'stock' => $newStockValue
        ]
    ],
    $context
);
```

## Disabling Shopware's stock management system

You can disable `\Shopware\Core\Content\Product\Stock\OrderStockSubscriber` entirely with the `stock.enable_stock_management` configuration setting.

## Implementing your own stock storage

Similar to the example above "Loading stock information from a different source" you can update a different database table or service, or implement custom inventory systems such as multi warehouse by decorating the `alter` method. 
This method is triggered with an array of `StockAlteration`'s. Which contain the Product & Line Item ID's, the old quantity and the new quantity. 

This method is triggered whenever an order is created or transitioned through the various states.

## Listening to entity delete events

The `BeforeDeleteEvent` has been renamed to `\Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent`. Please update your usages:

Before:

```php
/**
 * @return array<string, string>
 */
public static function getSubscribedEvents(): array
{
    return [
        \Shopware\Core\Framework\DataAbstractionLayer\Event\BeforeDeleteEvent::class => 'onBeforeDelete',
    ];
}
```

After:

```php
/**
 * @return array<string, string>
 */
public static function getSubscribedEvents(): array
{
    return [
        \Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent::class => 'onBeforeDelete',
    ];
}
```
The upcoming release includes a crucial breaking change aimed at resolving a major issue in how repository data is handled for the Admin Extension SDK. This change will be introduced in the next minor version.

The change only affects your app if you are utilizing properties within the context. With this modification, an empty context object will be returned within your Entity. You will receive a custom context object containing your specific context changes only when you also send a custom context object to the admin.

The final context will be merged with the default context in the administration. This allows you to use the default context to access all the necessary data.

# 6.5.4.0
* Update your thumbnails by running command: `media:generate-thumbnails`
## Generic type template for EntityRepository
The `EntityRepository` class now has a generic type template.
This allows to define the entity type of the repository, which improves the IDE support and static code analysis.
Usage:

```php
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class MyService
    /**
     * @param EntityRepository<ProductCollection> $productRepository
     */
    public function __construct(private readonly EntityRepository $productRepository)
    {}

    public function doSomething(Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));

        $products = $this->productRepository->search($criteria, $context)->getEntities();
        // $products is now inferred as ProductCollection
    }
```
## Clean duplicated theme images
With [4457](https://github.com/shopware/shopware/issues/4457) we fixed an issue with duplicated theme images on `system:update` and `theme:refresh`.
This fix will only prevent future duplicates. In order to remove already existing duplicates from your setup, follow these steps:

1. Open the administration media section
2. Open the folder `Theme Media` check for duplicated images with the suffix `(x)`, where x is a number. For example `defaultThemePreview_(1).jpg`, `defaultThemePreview_(2)` etc.
3. Check the id of the images without a `(x)` suffix, these are the original images
4. Go to the database table `theme` and check the field `preview_media_id`
5. If it is not the id of the original image, change it to that. Otherwise leave it as is.
6. Check the field `base_config`. This field is a JSON field. Check if there is an UUID inside, which refers to an image/media.
7. Check for all these UUIDs if they refer to the original image without a `(x)` prefix. If not, change the UUIDs to match the original image.
8. Check the database table `theme_media` for associations for the theme with duplicated images (with the suffix) and delete all of them.
9. Now you should be able to delete these duplicates in the administration media section in the folder `Theme Media`
10. Now do a `composer theme:refresh`

This comment on github could also be helpful: [github how to clean theme media](https://github.com/shopware/platform/discussions/3254#discussioncomment-6666360)
The images should not be doubled again.


# 6.5.3.0
## The app custom trigger and the app action can be defined in one xml file.
Since v6.5.2.0, we can define the flow custom trigger and the flow app action in one XML file.
To do that, we add the `Shopware\Core\Framework\App\Flow\Schema\flow-1.0.xsd` to support defining both of them.

* ***Example***
```xml
<flow-extensions xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="flow-1.0.xsd">
    <flow-events>
        <flow-event>...</flow-event>
    </flow-events>
    <flow-actions>
        <flow-action>...</flow-action>
    </flow-actions>
</flow-extensions>
```
## Deprecation of `ProductListingFeaturesSubscriber`
With 6.6 the `ProductListingFeaturesSubscriber` is removed. This is currently responsible for evaluating the listing request parameters and applying them to the criteria.

In the future this will no longer happen via the corresponding events but via the `AbstractListingProcessor`, which follows a more service oriented approach.

If you dispatch one of the following events yourself, and expect the subscriber to process the corresponding data, you should now call the `CompositeProcessor` instead:

```php
// before
class MyClass
{
    public function load(Criteria $criteria, SalesChannelContext $context) {
        $this->dispatcher->dispatch(
            new ProductListingCriteriaEvent($criteria, $context, $request)
        );
        
        $result = $this->loadListing($request, $criteria, $context);
        
        $this->dispatcher->dispatch(
            new ProductListingResultEvent($result, $context, $request)
        );
        
        return $result;
    }
}

// after
class MyClass
{
    public function __construct(private readonly CompositeProcessor $listingProcessor) {}
    
    public function load(Criteria $criteria, SalesChannelContext $context) 
    {
        $this->listingProcessor->prepare($request, $criteria, $context);
        
        $result = $this->loadListing($request, $criteria, $context);
        
        $this->listingProcessor->process($request, $result, $context);
        
        return $result;
    }
}
```
## MediaSerializer
### deserialize
The deserialize method of src/Core/Content/ImportExport/DataAbstractionLayer/Serializer/Entity/MediaSerializer.php was changed such that the filename will be urldecoded before saving it to the cacheMediaFiles.
Previously, encoded url raised an error during validateFileNameDoesNotContainForbiddenCharacter when importing them, because they contained % signs. 
On the other hand, not encoding urls raised an "Invalid media url" exception.

This change allows importing medias with filenames that contain special characters by supplying an encoded url in the import file.
* Changed behavior of text block element, which is able to switch between languages in product detail page

# 6.5.2.0
The `context` property is used instead of `contextData` property in `src/Core/Content/Media/Message/GenerateThumbnailsMessage` due to the `context` data is serialized in context source

## Update to Symfony 6.3
Shopware now uses Symfony version 6.3, please make sure your plugins are compatible.

## Introduce BeforeLoadStorableFlowDataEvent
The event is dispatched before the flow storer restores the data, so you can customize the criteria before passing it to the entity repository

**Reference: Shopware\Core\Content\Flow\Events\BeforeLoadStorableFlowDataEvent**

**Examples:**

```php
class OrderStorer extends FlowStorer
{
    public function restore(StorableFlow $storable): void
    {
        ...
        $criteria = new Criteria();
        $criteria->addAssociations([
            'orderCustomer',
            'lineItems.downloads.media',
        ]);
        $event = new BeforeLoadStorableFlowDataEvent(
            OrderDefinition::ENTITY_NAME,
            $criteria,
            $context,
        );

        $this->dispatcher->dispatch($event, $event->getName());

        $order = $this->orderRepository->search($criteria, $context)->get($orderId);
        ...
    }
}

class YourBeforeLoadStorableFlowOrderDataSubscriber implements EventSubscriberInterface
    public static function getSubscribedEvents()
    {
        return [
            'flow.storer.order.criteria.event' => 'handle',
        ];
    }

    public function handle(BeforeLoadStorableFlowDataEvent $event): void
    {
        $criteria = $event->getCriteria();
        
        // Add new association
        $criteria->addAssociation('tags');
    }
}
```
If you are relying on the association `import_export_log.file`, please associate the definition directly with the criteria because we will remove autoload from version 6.6.0.0.
* Renamed error code from `FRAMEWORK__STORE_CANNOT_DOWNLOAD_PLUGIN_MANAGED_BY_SHOPWARE` to `FRAMEWORK__STORE_CANNOT_DELETE_COMPOSER_MANAGED`

# 6.5.1.0

If you are relying on these associations:
 `order.stateMachineState`
 `order_transaction.stateMachineState`
 `order_delivery.stateMachineState`
 `order_delivery.shippingOrderAddress`
 `order_transaction_capture.stateMachineState`
 `order_transaction_capture_refund.stateMachineState`
 `tax_rule.type`
please associate the definitions directly with the criteria because we will remove autoload from version 6.6.0.0.
## Deprecated diverse flow storer classes and interfaces in favor of the new `ScalarValuesStorer` class 
We deprecated diverse flow storer interfaces in favor of the new `ScalarValuesAware` class. The new `ScalarValuesAware` class allows to store scalar values much easier for flows without implementing own storer and interface classes. 
If you implemented one of the deprecated interfaces or implemented an own interface and storer class to store simple values, you should replace it with the new `ScalarValuesAware` class. 

```php

// before
class MyEvent extends Event implements \Shopware\Core\Content\Flow\Dispatching\Aware\UrlAware
{
    private string $url;

    public function __construct(string $url)
    {
        $this->url = $url;
    }

    public function getUrl(): string
    {
        return $this->url;
    }
    
    // ...
}

// after

class MyEvent extends Event implements \Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware
{
    private string $url;

    public function __construct(string $url)
    {
        $this->url = $url;
    }

    public function getScalarValues(): array
    {
        return [
            'url' => $this->url,
            // ...
        ];
    }
    
    // ...
}
```

The deprecated flow storer interfaces are:
* `ConfirmUrlAware`
* `ContactFormDataAware`
* `ContentsAware`
* `ContextTokenAware`
* `DataAware`
* `EmailAware`
* `MediaUploadedAware`
* `NameAware`
* `RecipientsAware`
* `ResetUrlAware`
* `ReviewFormDataAware`
* `ScalarStoreAware`
* `ShopNameAware`
* `SubjectAware`
* `TemplateDataAware`
* `UrlAware`
## Marking media as used 
If your plugin references media in a way that is not understood by the DAL, for example in JSON blobs, it is now possible for your plugin to inform the system that this media is used and should not be deleted when the `\Shopware\Core\Content\Media\UnusedMediaPurger` service is executed.
To do this, you need to create a listener for the `Shopware\Core\Content\Media\Event\UnusedMediaSearchEvent` event. This event can be called multiple times during the cleanup task with different sets of media ID's scheduled to be deleted. Your listener should check if any of the media ID's passed to the event are used by your plugin and mark them as used by calling the `markMediaAsUsed` method on the event object with an array of the used media ID's.
You can get the media ID's scheduled for deletion from the event object by calling the `getMediaIds` method.

See the following implementations for an example: 
* \Shopware\Core\Content\Cms\Subscriber\UnusedMediaSubscriber
* \Shopware\Storefront\Theme\Subscriber\UnusedMediaSubscriber
## Fix method signatures to comply with parent class/interface signature
The following method signatures were changed to comply with the parent class/interface signature:
**Visibility changes:**
* Method `configure()` was changed from public to protected in:
  * `Shopware\Storefront\Theme\Command\ThemeCompileCommand`
* Method `execute()` was changed from public to protected in:
  * `Shopware\Core\Framework\Adapter\Asset\AssetInstallCommand`
  * `Shopware\Core\DevOps\System\Command\SystemDumpDatabaseCommand`
  * `Shopware\Core\DevOps\System\Command\SystemRestoreDatabaseCommand`
  * `Shopware\Core\DevOps\Docs\App\DocsAppEventCommand`
  * 
* Method `getExpectedClass()` was changed from public to protected in:
  * `Shopware\Storefront\Theme\ThemeSalesChannelCollection`
  * `Shopware\Core\Framework\Store\Struct\PluginRecommendationCollection`
  * `Shopware\Core\Framework\Store\Struct\PluginCategoryCollection`
  * `Shopware\Core\Framework\Store\Struct\LicenseDomainCollection`
  * `Shopware\Core\Framework\Store\Struct\PluginRegionCollection`
  * `Shopware\Core\Content\ImportExport\Processing\Mapping\UpdateByCollection`
  * `Shopware\Core\Content\ImportExport\Processing\Mapping\MappingCollection`
  * `Shopware\Core\Content\Product\Aggregate\ProductCrossSellingAssignedProducts\ProductCrossSellingAssignedProductsCollection`
  * `Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingCollection`
  * `Shopware\Core\Content\Product\SalesChannel\CrossSelling\CrossSellingElementCollection`
  * `Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection`
  * `Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscountPrice\PromotionDiscountPriceCollection`
* Method `getParentDefinitionClass()` was changed from public to protected in:
  * `Shopware\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics\SalesChannelAnalyticsDefinition`
  * `Shopware\Core\Content\ImportExport\ImportExportProfileTranslationDefinition`
  * `Shopware\Core\Content\Product\Aggregate\ProductCrossSellingAssignedProducts\ProductCrossSellingAssignedProductsDefinition`
  * `Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingDefinition`
  * `Shopware\Core\Content\Product\Aggregate\ProductFeatureSetTranslation\ProductFeatureSetTranslationDefinition`
  * `Shopware\Core\Checkout\Promotion\Aggregate\PromotionTranslation\PromotionTranslationDefinition`
* Method `getDecorated()` was changed from public to protected in:
  * `Shopware\Core\System\Country\SalesChannel\CachedCountryRoute`
  * `Shopware\Core\System\Country\SalesChannel\CachedCountryStateRoute`
* Method `getSerializerClass()` was changed from public to protected in:
  * `Shopware\Core\Framework\DataAbstractionLayer\Field\StateMachineStateField`

**Parameter type changes:**
* Changed parameter `$url` to `string` in:
  * `Shopware\Storefront\Framework\Cache\ReverseProxy\ReverseProxyCache#purge()`
* Changed parameter `$data` and `$format` to `string` in:
  * `Shopware\Core\Framework\Struct\Serializer\StructDecoder#decode()`
  * `Shopware\Core\Framework\Struct\Serializer\StructDecoder#supportsDecoding()`
  * `Shopware\Core\Framework\Api\Serializer\JsonApiDecoder#decode()`
  * `Shopware\Core\Framework\Api\Serializer\JsonApiDecoder#supportsDecoding()`
* Changed parameter `$storageName` and `$propertyName` to `string` in:
  * `Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields#__construct()`
* Changed parameter `$event` to `object` in:
  * `Shopware\Core\Framework\Event\NestedEventDispatcher#dispatch()`
* Changed parameter `$listener` to `callable` in:
  * `Shopware\Core\Framework\Event\NestedEventDispatcher#removeListener()`
  * `Shopware\Core\Framework\Event\NestedEventDispatcher#getListenerPriority()`
  * `Shopware\Core\Framework\Webhook\WebhookDispatcher#removeListener()`
  * `Shopware\Core\Framework\Webhook\WebhookDispatcher#getListenerPriority()`
* Changed parameter `$constraints` to `Symfony\Component\Validator\Constraint|array|null` in:
  * `Shopware\Core\Framework\Validation\HappyPathValidator#validate()`
* Changed parameter `$object` to `object`, `$propertyName` to `string`, `$groups` to `string|Symfony\Component\Validator\Constraints\GroupSequence|array|null` and `$objectOrClass` to `object|string` in:
  * `Shopware\Core\Framework\Validation\HappyPathValidator#validateProperty()`
  * `Shopware\Core\Framework\Validation\HappyPathValidator#validatePropertyValue()`
* Changed parameter `$record` to `iterable` in:
  * `Shopware\Core\Content\ImportExport\Processing\Pipe\EntityPipe#in()`
* Changed parameter `$warmupDir` to `string` in:
  * `Shopware\Core\Kernel#reboot()`
## Twig cache independent from kernel cache dir

You can now use the `twig.cache` configuration to configure the directory where twig caches are stored as described in the [symfony docs](https://symfony.com/doc/current/reference/configuration/twig.html#cache). This is independent from the `kernel.cache_dir` configuration, but by default it will still fallback to the `%kernel.cache_dir%/twig` directory.
This is useful when the `kernel.cache_dir` is configured to be a read-only directory.
## Becomes internal or private
* Deprecated `AbstractIncrementer::getDecorated`, increment are not designed for decoration pattern
* Deprecated `MySQLIncrementer`, implementation will be private, use abstract class for type hints
* Deprecated `RedisIncrementer`, implementation will be private, use abstract class for type hints
* Deprecated `ImportExport\PriceFieldSerializer::isValidPrice`, function will be private in v6.6
* Deprecated `CsvReader::loadConfig`, function will be private in v6.6
* Deprecated `NewsletterSubscribeRoute.php`, function will be private in v6.6
## App scripts have access to shopware version

App scripts now have access to the shopware version via the `shopware.version` global variable.
```twig
{% if version_compare('6.4', shopware.version, '<=') %}
    {# 6.4 or lower compatible code #}
{% else %}
    {# 6.5 or higher compatible code #}    
{% endif %}
```

# Administration

## Node requirements increased

Increased Node version to 18 and NPM to version 8 or 9.

## Removal of old icons:

* Replace any old icon your integration uses with its successor. A mapping can be found here `src/Administration/Resources/app/administration/src/app/component/base/sw-icon/legacy-icon-mapping.js`.
* The object keys of the json file are the legacy icons. The values the replacement.
* In the next major, the icons will have no space around them by default. This could eventually lead to bigger looking icons in some places. If this is the case you need to adjust the styling with CSS so that it matches your wanted look.

### Example:
Before:

```html
<sw-icon name="default-object-image"/>
```

After:
```html
<sw-icon name="regular-image"/>
```

## sw-simple-search-field property changed from `search-term` to `value`

Use `value` property instead.

Before:
```html
<sw-simple-search-field
  …
  :search-term="term"
  …
/>
```

After:
```html
<sw-simple-search-field
  …
  :value="term"
  …
/>
```

## Exchange sw-order-state-select

To get the new state selection exchange your `sw-order-state-select` component uses with `sw-order-state-select-v2`.
No required props have been added or removed, only the styling and layout of the component changed.

## Deprecated action:

* action `setAppModules` in `src/app/state/shopware-apps.store.ts` is removed
* action `setAppModules` in `src/app/state/shopware-apps.store.ts` is removed

# Core

## Update minimum PHP version to 8.1
Shopware 6 now requires at least PHP 8.1.0. Please update your PHP version to at least 8.1.0.
Refer to the upgrade guide to [v8.0](https://www.php.net/manual/en/migration80.php) and [v8.1](https://www.php.net/manual/en/migration81.php) for more information.

## Update to Symfony 6.2
Shopware now uses symfony components in version 6.2, please make sure your plugins are compatible.
Refer to the upgrade guides to [v6.0](https://github.com/symfony/symfony/blob/6.2/UPGRADE-6.0.md), [v6.1](https://github.com/symfony/symfony/blob/6.2/UPGRADE-6.1.md) and [v6.2](https://github.com/symfony/symfony/blob/6.2/UPGRADE-6.2.md).

## Change Elasticsearch DSL/SDK library OpenSearch
We changed the used Elasticsearch DSL library to `shyim/opensearch-php-dsl`, instead of `ongr/elasticsearch-dsl`.
It is a fork of the ONGR library and migrating should be straight forward. You need to change the namespace of the used classes from `ONGR\ElasticsearchDSL` to `OpenSearchDSL`.
Before:
```php
use ONGR\ElasticsearchDSL\Aggregation\AbstractAggregation;
```
After:
```php
use OpenSearchDSL\Aggregation\AbstractAggregation;
```

Also, we changed the Elasticsearch PHP SDK to OpenSearch

## Change of environment variables

* Renamed following environment variables to use more generic environment variable name used by cloud providers:
    * `SHOPWARE_ES_HOSTS` to `OPENSEARCH_URL`
    * `MAILER_URL` to `MAILER_DSN`

You can change this variable back in your installation using a `config/packages/elasticsearch.yaml` with

```yaml
elasticsearch:
    hosts: "%env(string:SHOPWARE_ES_HOSTS)%"
```

or prepare your env by replacing the var with the new one like

```yaml
elasticsearch:
    hosts: "%env(string:OPENSEARCH_URL)%"
```

## Sync api changes
We removed the single record behavior in the sync api. This means, that all operations and records are now handled as a collection and validated and written within one transaction.
The headers `HTTP_Fail-On-Error` and `single-operation` were removed. The `single-operation` header is now always true. 

## DBAL upgrade

We upgraded DBAL from 2.x to 3.x. Please take a look at the [DBAL upgrade information](https://github.com/doctrine/dbal/blob/3.6.0/UPGRADE.md) itself to see if you need to adjust your code.

## Changed default queue name
Before 6.5 our default message queue transport name were `default`. We changed this to `async` to ensure that application which are running with the 6.5 aren't handling the message of the 6.4.

You're now able to configure own transports and dispatch message over your own transports by adding new transports within the `framework.messenger.transports` configuration. For more details, see official symfony documentation: https://symfony.com/doc/current/messenger.html

## Json encoded message queue messages
Before 6.5, we php-serialized all message queue messages and php-unserialize them. This causes different problems, and we decided to change this format to json. This format is also recommend from symfony and other open source projects. Due to this change, you may have to change your messages when you added some php objects to the message. If you have simple PHP objects within a message, the symfony serializer should be able to encode and decode your objects. For more information take a look to the offical symfony documentation: https://symfony.com/doc/current/messenger.html#serializing-messages
Since v6.6.0.0, `ContextTokenResponse` class won't return the contextToken value in the response body anymore, please using the header `sw-context-token` instead

## Changed `HttpCache`, `Entity` and `NoStore` configurations for routes

The Route-level configurations for `HttpCache`, `Entity` and `NoStore` where changed from custom annotations to `@Route` defaults.
The reasons for those changes are outlined in this [ADR](/adr/2022-02-09-controller-configuration-route-defaults.md) and for a lot of former annotations this change was already done previously.
Now we also change the handling for the last three annotations to be consistent and to allow the removal of the abandoned `sensio/framework-extra-bundle`.

This means the `@HttpCache`, `@Entity`, `@NoStore` annotations are deprecated and have no effect anymore, the configuration no needs to be done as `defaults` in the `@Route` annotation.

Before:
```php
/**
 * @Route("/my-route", name="my.route", methods={"GET"})
 * @NoStore
 * @HttpCache(maxage="3600", states={"cart.filled"})
 * @Entity("product")
 */
public function myRoute(): Response
{
    // ...
}
```

After:
```php
/**
 * @Route("/my-route", name="my.route", methods={"GET"}, defaults={"_noStore"=true, "_httpCache"={"maxage"="3600", "states"={"cart.filled"}}, "_entity"="product"})
 */
public function myRoute(): Response
{
    // ...
}
```

## Only mapped properties encoded
The `\Shopware\Core\System\SalesChannel\Api\StructEncoder` now only encodes entity properties which are mapped in the entity definition.  If you have custom code which relies on the encoder to encode properties which aren't mapped in the entity definition, you need to adjust your code to map these properties in the entity definition.

## `EntityRepositoryInterface` removal

All type hints from EntityRepositoryInterface should be changed to EntityRepository, you can use [rector](https://github.com/FriendsOfShopware/shopware-rector) for that.

We removed the `EntityRepositoryInterface` & `SalesChannelRepositoryInterface` classes and declared the `EntityRepository` & `SalesChannelRepository` as final.
Therefore, if you implemented an own repository class for your entities, you have to remove this now.
To modify the repository calls, you can use one of the following events:
* `BeforeDeleteEvent`: Allows an access point for before and after deleting the entity
* `EntitySearchedEvent`: Allows access points to the criteria for search and search-ids
* `PreWriteValidationEvent`/`PostWriteValidationEvent`: Allows access points before and after the entity written
* `SalesChannelProcessCriteriaEvent`: Allows access to the criteria before the entity is search within a sales channel scope

Additionally, you have to change your type hints from `EntityRepositoryInterface` & `SalesChannelRepositoryInterface` to `EntityRepository` or `SalesChannelRepository`:

## Removed repository decorators:

Removed the following repository decorators:
* `MediaRepositoryDecorator`
* `MediaThumbnailRepositoryDecorator`
* `MediaFolderRepositoryDecorator`
* `PaymentMethodRepositoryDecorator`

If you used one of the repositories and type a hint against this specific classes,
you have to change your type hints to `EntityRepository`:

## Removed unused entity fields

Following, entity properties/methods have been removed:

- `product.blacklistIds`
- `product.whitelistIds`
- `seo_url.isValid`

## Shipping method active flag changes

When you create a new shipping method, the default value for the active flag is false, i.e. the method is inactive after saving.
Please provide the active value if you create shipping methods over the API.

## Flow builder doesn't use event manager anymore

* In the next major, the flow actions aren't executed over the symfony events anymore; we'll remove the dependence from `EventSubscriberInterface` in `Shopware\Core\Content\Flow\Dispatching\Action\FlowAction`.
* In the next major, the flow actions aren't executed via symfony events anymore;
  we'll remove the dependency from `EventSubscriberInterface` in `Shopware\Core\Content\Flow\Dispatching\Action\FlowAction`.
  That means, all the flow actions extending `FlowAction` get the "services" tag.
* The flow builder will execute the actions when calling the `handleFlow` function directly, instead of dispatching an action event.
* To get an action service in flow builder, we need to define the tag action service with an unique key, which should be an action name.
* The flow action data is stored in `StorableFlow $flow`, so you should use `$flow->getStore('order_id')` or `$flow->getData('order')` instead of `$flowEvent->getOrder`.
    * Use `$flow->getStore($key)` if you want to get the data from `aware` interfaces. Example: `order_id` in `OrderAware` or `customer_id` from `CustomerAware`.
    * Use `$flow->getData($key)` if you want to get the data from original events or additional data. Example: `order`, `customer` or `contactFormData`.

**before**
```xml
 <service id="Shopware\Core\Content\Flow\Dispatching\Action\SendMailAction">
    ...
    <tag name="flow.action"/>
</service>
```

```php
class FlowExecutor
{
    ...
    
    $this->dispatcher->dispatch($flowEvent, $actionname);
    
    ...
}

abstract class FlowAction implements EventSubscriberInterface
{
    ...
}

class SendMailAction extends FlowAction
{
    ...
    public static function getSubscribedEvents()
    {
        return ['action.name' => 'handle'];
    }
    
    public function handle(FlowEvent $event)
    {
        ...
        
        $orderId = $event->getOrderId();
        
        $contactFormData = $event->getConta();
        
        ...
    }
}
```

**after**
```xml
 <service id="Shopware\Core\Content\Flow\Dispatching\Action\SendMailAction">
    ...
    <tag name="flow.action" key="action.mail.send" />
</service>
```

```php
class FlowExecutor
{
    ...
    
    $actionService = $actions[$actionName];
    
    $actionService->handleFlow($storableFlow);
    
    ...
}

abstract class FlowAction
{
    ...
}

class SendMailAction extends FlowAction
{
    ...
    // The `getSubscribedEvents` function has been removed.
    
    public function handleFlow(StorableFlow $flow)
    {
        ...
        
        $orderId = $flow->getStore('order_id');
        
        $contactFormData = $event->getData('contactFormData');
        
        ...
    }
}
```

## Remove static address formatting:

* Deprecated fixed address formatting, use `@Framework/snippets/render.html.twig` instead, applied on:
    - `src/Storefront/Resources/views/storefront/component/address/address.html.twig`
    - `src/Core/Framework/Resources/views/documents/delivery_note.html.twig`
    - `src/Core/Framework/Resources/views/documents/includes/letter_header.html.twig`

## Remove "marc1706/fast-image-size" dependency

The dependency on the "marc1706/fast-image-size" library was removed, requires the library yourself if you need it.

## Moved CheapestPrice to `SalesChannelProductEntity`

The CheapestPrice will only be resolved in SalesChannelContext, thus it moved from the basic `ProductEntity` to the `SalesChannelProductEntity`.
If you rely on the CheapestPrice props of the ProductEntity in your plugin, make sure that you're in a SalesChannelContext and use the `sales_channel.product.repository` instead of the `product.repository`

### Before
```
private EntityRepositoryInterface $productRepository;

public function custom(SalesChannelContext $context): void
{
    $products = $this->productRepository->search(new Criteria(), $context->getContext());
    /** @var ProductEntity $product */
    foreach ($products as $product) {
        $cheapestPrice = $product->getCheapestPrice();
        // do stuff with $cheapestPrice
    }
}
```

### After

```
private SalesChannelRepositoryInterface $salesChannelProductRepository;

public function custom(SalesChannelContext $context): void
{
    $products = $this->salesChannelProductRepository->search(new Criteria(), $context);
    /** @var SalesChannelProductEntity $product */
    foreach ($products as $product) {
        $cheapestPrice = $product->getCheapestPrice();
        // do stuff with $cheapestPrice
    }
}
```

## Signature change of property group sorter and max purchase calculator
You have to change the signature of your `AbstractProductMaxPurchaseCalculator` implementation as follows:
```php
// before
abstract public function calculate(SalesChannelProductEntity $product, SalesChannelContext $context): int;

// after
abstract public function calculate(Entity $product, SalesChannelContext $context): int;
```

You have to change the signature of your `PropertyGroupSorter` implementation as follows:
```php
// before
abstract public function sort(PropertyGroupOptionCollection $groupOptionCollection): PropertyGroupCollection;

// after
abstract public function sort(EntityCollection $options): PropertyGroupCollection;
```

## Seo url refactoring

Seo url generation will now only generate urls when the entity is also assigned to this sales channel.
To archive this `\Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface::prepareCriteria` gets as second parameter the SalesChannelEntity which will be currently proceed, to filter the criteria for this scope.

To make your Plugin already compatible for next major version you can use ReflectionClass with an if condition to avoid interface issues

$criteria->addFilter(new EqualsFilter('visibilities.salesChannelId', $salesChannel->getId()));

```php
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;

if (($r = new ReflectionClass(SeoUrlRouteInterface::class)) && $r->hasMethod('prepareCriteria') && $r->getMethod('prepareCriteria')->getNumberOfRequiredParameters() === 2) {
    class MyPluginRoute implements SeoUrlRouteInterface
    {
        public function getConfig(): SeoUrlRouteConfig
        {
            // your logic
        }
    
        public function prepareCriteria(Criteria $criteria, SalesChannelEntity $salesChannel): void
        {
            // your logic
        }
        
        public function getMapping(Entity $product, SalesChannelEntity $salesChannel): SeoUrlMapping
        {
            // your logic
        }
    }
} else {
    class MyPluginRoute implements SeoUrlRouteInterface
    {
        public function getConfig(): SeoUrlRouteConfig
        {
            // your logic
        }
    
        public function prepareCriteria(Criteria $criteria): void
        {
            // your logic
        }
        
        public function getMapping(Entity $product, ?SalesChannelEntity $salesChannel): SeoUrlMapping
        {
            // your logic
        }
    }
}
```

## LanguageId of SalesChannel in SalesChannelContext will not be overridden anymore
The languageId of the SalesChannel inside the SalesChannelContext will not be overridden by the current Language of the context anymore.
So if you need the current language from the context use `$salesChannelContext->getLanguageId()` instead of relying on the languageId of the SalesChannel.

### Before

```php
$currentLanguageId = $salesChannelContext->getSalesChannel()->getLanguageId();
```

### After

```php
$currentLanguageId = $salesChannelContext->getLanguageId();
```

### Store-Api

When calling the `/store-api/context` route, you now get the core context information in the response.
Instead of using `response.salesChannel.languageId`, please use `response.context.languageIdChain[0]` now.

## Refactoring of `HreflangLoader`

The protected method `\Shopware\Core\Content\Seo\HreflangLoader::generateHreflangHome()` was removed, use `\Shopware\Core\Content\Seo\HreflangLoader::load()` with `route = 'frontend.home.page'` instead.

### Before

```php
class CustomHrefLoader extends HreflangLoader
{
    public function someFunction(SalesChannelContext $salesChannelContext)
    {
        return $this->generateHreflangHome($salesChannelContext);
    }
}
```

### After

```php
class CustomHrefLoader extends HreflangLoader
{
    public function someFunction(SalesChannelContext $salesChannelContext)
    {
        return $this->load(
            new HreflangLoaderParameter('frontend.home.page', [], $salesChannelContext)
        );
    }
}
```

## Removal of the `psalm` dependency

The platform doesn't rely on `psalm` for static analysis anymore, but solely uses `phpstan` for that purpose.
Therefore, the `psalm` dev-dependency was removed.
If you used the dev-dependency from platform in your project, please install the `psalm` package directly into your project.

## Double OptIn customers will be active by default
If the double opt in feature for the customer registration is enabled the customer accounts will be set active by default starting from Shopware 6.6.0.0. The validation now only considers if the customer has the double opt in registration enabled, i.e. the database value `customer.double_opt_in_registration` equals `1` and if there exists an double opt in date in `customer.double_opt_in_confirm_date`.

## Custom fields in cart
Custom fields will now be removed from the cart for performance reasons. Add the to the allow list with CartBeforeSerializationEvent if you need them in cart.

## Changed default message behavior
By default, all messages which are dispatched via message queue, are handled synchronous. Before 6.5 we had a message queue decoration to change this default behavior to asynchronous. This decoration has now been removed. We provide a simple opportunity to restore the old behavior by implementing the `AsyncMessageInterface` interface to dispatch message synchronous.

```php
class EntityIndexingMessage implements AsyncMessageInterface
{
    // ...
}
```

## Remove old database migration trigger logic

The `addForwardTrigger()`, `addBackwardTrigger()` and `addTrigger()` methods of the `MigrationStep` class were removed, use `createTrigger()` instead.
Don't rely on the state of already executed migrations in your database triggers anymore!
Additionally, the `@MIGRATION_{migration}_IS_ACTIVE` DB connection variables aren't set at kernel boot anymore.

## Removal of `\Shopware\Core\Framework\Event\FlowEvent`

We removed `\Shopware\Core\Framework\Event\FlowEvent`, since Flow Actions aren't executed via symfony's event system anymore.
You should implement the `handleFlow()` method in your `FlowAction` and tag your actions as `flow.action`.

## Internal Migrations

All DB migration steps are now considered `@internal`, as they never should be extended or adjusted afterward.

## Removal of `/api/_action/database`

The `/api/_action/database` endpoint was removed; this means the following routes aren't available anymore:
* `POST /api/_action/database/sync-migration`
* `POST /api/_action/database/migrate`
* `POST /api/_action/database/migrate-destructive`

The migrations can't be executed over the API anymore. Database migrations should be only executed over the CLI.

## Deprecated the `OpenApiPathsEvent`:

* Move the schema described by your `@OpenApi` / `@OA` annotations to json files.
* New the openapi specification is now loaded from `$bundlePath/Resources/Schema/`.
* For an examples look at `src/Core/Framework/Api/ApiDefinition/Generator/Schema`.

## Removed `DatabaseInitializer`

Removed class `\Shopware\Core\Maintenance\System\Service\DatabaseInitializer`, use `SetupDatabaseAdapter` instead.

## Removed `JwtCertificateService`

Removed class `\Shopware\Recovery\Common\Service\JwtCertificateService`, use `JwtCertificateGenerator` instead.

### Removal of `\Shopware\Core\System\NumberRange\ValueGenerator\Pattern\ValueGeneratorPatternRegistry::getPatternResolver()`

We removed the `ValueGeneratorPatternRegistry::getPatternResolver()` method, please call the `generatePattern()` method now directly.

Before:
```php
$patternResolver = $this->valueGeneratorPatternRegistry->getPatternResolver($pattern);
if ($patternResolver) {
    $generated .= $patternResolver->resolve($configuration, $patternArg, $preview);
} else {
    $generated .= $patternPart;
}
```

After:

```php
$generated .= $this->valueGeneratorPatternRegistry->generatePattern($pattern, $patternPart, $configuration, $patternArg, $preview);
```

### Removal of `ValueGeneratorPatternInterface`

We removed the `Shopware\Core\System\NumberRange\ValueGenerator\Pattern\ValueGeneratorPatternInterface`.
If you've implemented a custom value pattern please use the abstract class `Shopware\Core\System\NumberRange\ValueGenerator\Pattern\AbstractValueGenerator`.

```php
class CustomPattern implements ValueGeneratorPatternInterface
{
    public function resolve(NumberRangeEntity $configuration, ?array $args = null, ?bool $preview = false): string
    {
        return $this->createPattern($configuration->getId(), $configuration->getPattern());
    }
    
    public function getPatternId(): string
    {
        return 'custom';
    }
}
```
After:
```php
class CustomIncrementStorage extends AbstractValueGenerator
{
    public function generate(array $config, ?array $args = null, ?bool $preview = false): string
    {
        return $this->createPattern($config['id'], $config['pattern']);
    }
    
    public function getPatternId(): string
    {
        return 'custom';
    }
    
    public function getDecorated(): self
    {
        return $this->decorated;
    }
}
```

## Removal of `\Shopware\Core\Framework\Adapter\Twig\EntityTemplateLoader::clearInternalCache()`

We removed `\Shopware\Core\Framework\Adapter\Twig\EntityTemplateLoader::clearInternalCache()`, use `reset()` instead.

## Refactoring of Number Ranges

We refactored the number range handling, to be faster and allow different storages to be used.

### Removal of `IncrementStorageInterface`

We removed the `Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementStorageInterface`.
If you've implemented a custom increment storage please use the abstract class `Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\AbstractIncrementStorage`.
Before:

```php
class CustomIncrementStorage implements IncrementStorageInterface
{
    public function pullState(\Shopware\Core\System\NumberRange\NumberRangeEntity $configuration): string
    {
        return $this->increment($configuration->getId(), $configuration->getPattern());
    }
    
    public function getNext(\Shopware\Core\System\NumberRange\NumberRangeEntity $configuration): string
    {
        return $this->get($configuration->getId(), $configuration->getPattern());
    }
}
```

After:

```php
class CustomIncrementStorage extends AbstractIncrementStorage
{
    public function reserve(array $config): string
    {
        return $this->increment($config['id'], $config['pattern']);
    }
    
    public function preview(array $config): string
    {
        return $this->get($config['id'], $config['pattern']);
    }
    
    public function getDecorated(): self
    {
        return $this->decorated;
    }
}
```

## New Profiling pattern
Due to a new and better profiling pattern we removed the following services:
* `\Shopware\Core\Profiling\Checkout\SalesChannelContextServiceProfiler`
* `\Shopware\Core\Profiling\Entity\EntityAggregatorProfiler`
* `\Shopware\Core\Profiling\Entity\EntitySearcherProfiler`
* `\Shopware\Core\Profiling\Entity\EntityReaderProfiler`

You can now use the `Profiler::trace()` function to add custom traces directly from your services.

## Skipping of the cart calculation if the cart is empty

If the cart is empty the cart calculation will be skipped.
This means that all `\Shopware\Core\Checkout\Cart\CartProcessorInterface` and `\Shopware\Core\Checkout\Cart\CartDataCollectorInterface` will not be executed anymore if the cart is empty.

## ArrayEntity::getVars():

The `ArrayEntity::getVars()` has been changed so that the `data` property is no longer in the payload but applied to the `root` level.
This change affects all entity definitions that don't have their own entity class defined.
The API routes shouldn't be affected, because they didn't work with an ArrayEntity before the change, so no before/after payload can be shown.

### Before

```php 
$entity = new ArrayEntity(['foo' => 'bar']);
assert($entity->getVars(), ['data' => ['foo' => 'bar'], 'foo' => 'bar']);
```

### After

```php
$entity = new ArrayEntity(['foo' => 'bar']);
assert($entity->getVars(), ['foo' => 'bar']);
```

## Deprecations in `Shopware\Core\Framework\Store\Services\StoreAppLifecycleService`

The class `StoreAppLifecycleService` has been marked as internal.

We also removed the `StoreAppLifecycleService::getAppIdByName()` method.

## Removal of `Shopware\Core\Framework\Store\Exception\ExtensionRequiresNewPrivilegesException`

We removed the `ExtensionRequiresNewPrivilegesException` exception.
Will be replaced with the internal `ExtensionUpdateRequiresConsentAffirmationException` exception to have a more generic one.

## Thumbnail repository flat ids delete
The `media_thumbnail.repository` had an own implementation of the `EntityRepository`(`MediaThumbnailRepositoryDecorator`) which breaks the nested primary key pattern for the `delete` call and allowed you providing a flat id array. If you used the repository in this way, you have to change the usage as follows:

### Before
```php
$repository->delete([$id1, $id2], $context);
```

### After
```php
$repository->delete([
    ['id' => $id1], 
    ['id' => $id2]
], $context);
```

## Extending `StringTemplateRenderer`

The class `StringTemplateRenderer` should not be extended and will become `final`.

# Storefront

## Bootstrap 5 upgrade

Bootstrap v5 introduces breaking changes in HTML, (S)CSS and JavaScript.
Below you can find a migration overview of the effected areas in the Shopware platform.
Please consider that we can't provide code migration examples for every possible scenario of a UI-Framework like Bootstrap.
You can find a full migration guide on the official Bootstrap website: [Migrating to v5](https://getbootstrap.com/docs/5.1/migration)

### HTML/Twig

The Update to Bootstrap v5 often contains the renaming of attributes and classes. Those need to be replaced.
However, all Twig blocks remain untouched so all template extensions will take effect.

#### Rename attributes and classes

* Replace `data-toggle` with `data-bs-toggle`
* Replace `data-dismiss` with `data-bs-dismiss`
* Replace `data-target` with `data-bs-target`
* Replace `data-offset` with `data-bs-offset`
* Replace `custom-select` with `form-select`
* Replace `custom-file` with `form-file`
* Replace `custom-range` with `form-range`
* Replace `no-gutters` with `g-0`
* Replace `custom-control custom-checkbox` with `form-check`
* Replace `custom-control custom-switch` with `form-check form-switch`
* Replace `custom-control custom-radio` with `form-check`
* Replace `custom-control-input` with `form-check-input`
* Replace `custom-control-label` with `form-check-label`
* Replace `form-row` with `row g-2`
* Replace `modal-close` with `btn-close`
* Replace `sr-only` with `visually-hidden`
* Replace `badge-*` with `bg-*`
* Replace `badge-pill` with `rounded-pill`
* Replace `close` with `btn-close`
* Replace `left-*` and `right-*` with `start-*` and `end-*`
* Replace `float-left` and `float-right` with `float-start` and `float-end`.
* Replace `border-left` and `border-right` with `border-start` and `border-end`.
* Replace `rounded-left` and `rounded-right` with `rounded-start` and `rounded-end`.
* Replace `ml-*` and `mr-*` with `ms-*` and `me-*`.
* Replace `pl-*` and `pr-*` with `ps-*` and `pe-*`.
* Replace `text-left` and `text-right` with `text-start` and `text-end`.

#### Replace .btn-block class with .d-grid wrapper

##### Before

```html
<a href="#" class="btn btn-block">Default button</a>
```

##### After

```html
<div class="d-grid">
    <a href="#" class="btn">Default button</a>
</div>
```

#### Remove .input-group-append wrapper inside .input-group

##### Before

```html
<div class="input-group">
    <input type="text" class="form-control">
    <div class="input-group-append">
        <button type="submit" class="btn">Submit</button>
    </div>
</div>
```

##### After

```html
<div class="input-group">
    <input type="text" class="form-control">
    <button type="submit" class="btn">Submit</button>
</div>
```

### SCSS

Please consider that the classes documented in "HTML/Twig" must also be replaced inside SCSS.

* Replace all mixin usages of `media-breakpoint-down()` with the current breakpoint, instead of the next breakpoint:
    * Replace `media-breakpoint-down(xs)` with `media-breakpoint-down(sm)`
    * Replace `media-breakpoint-down(sm)` with `media-breakpoint-down(md)`
    * Replace `media-breakpoint-down(md)` with `media-breakpoint-down(lg)`
    * Replace `media-breakpoint-down(lg)` with `media-breakpoint-down(xl)`
    * Replace `media-breakpoint-down(xl)` with `media-breakpoint-down(xxl)`
* Replace `$custom-select-*` variable with `$form-select-*`

### JavaScript/jQuery

With the update to Bootstrap v5, the jQuery dependency will be removed from the shopware platform.
We strongly recommend migrating jQuery implementations to Vanilla JavaScript.

#### Initializing Bootstrap JavaScript plugins

##### Before

```js
// Previously Bootstrap plugins were initialized on jQuery elements
const collapse = DomAccess.querySelector('.collapse');
$(collapse).collapse('toggle');
```

##### After

```js
// With Bootstrap v5 the Collapse plugin is instantiated and takes a native HTML element as a parameter
const collapse = DomAccess.querySelector('.collapse');
new bootstrap.Collapse(collapse, {
    toggle: true,
});
```

#### Subscribing to Bootstrap JavaScript events

##### Before

```js
// Previously Bootstrap events were subscribed using the jQuery `on()` method
const collapse = DomAccess.querySelector('.collapse');
$(collapse).on('show.bs.collapse', this._myMethod.bind(this));
$(collapse).on('hide.bs.collapse', this._myMethod.bind(this));
```

##### After

```js
// With Bootstrap v5 a native event listener is being used
const collapse = DomAccess.querySelector('.collapse');
collapse.addEventListener('show.bs.collapse', this._myMethod.bind(this));
collapse.addEventListener('hide.bs.collapse', this._myMethod.bind(this));
```

#### Still need jQuery?

In case you still need jQuery, you can add it to your own app or theme.
This is the recommended method for all apps/themes which don't have control over the Shopware environment in which they're running in.

* Extend the file `platform/src/Storefront/Resources/views/storefront/layout/meta.html.twig`.
* Use the block `layout_head_javascript_jquery` to add a `<script>` tag containing jQuery. **Only use this block to add jQuery**.
* This block is not deprecated and can be used in the long term beyond the next major version of shopware.
* Don't** use the `{{ parent() }}` call. This prevents multiple usages of jQuery. Even if multiple other plugins/apps use this method, the jQuery script will only be added once.
* Please use jQuery version `3.5.1` (slim minified) to avoid compatibility issues between different plugins/apps.
* If you don't want to use a CDN for jQuery, [download jQuery from the official website](https://releases.jquery.com/jquery/) (jQuery Core 3.5.1 - slim minified) and add it to `MyExtension/src/Resources/public/assets/jquery-3.5.1.slim.min.js`
* After executing `bin/console asset:install`, you can reference the file using the `assset()` function. See also: https://developer.shopware.com/docs/guides/plugins/plugins/storefront/add-custom-assets

```html
{% sw_extends '@Storefront/storefront/layout/meta.html.twig' %}

{% block layout_head_javascript_jquery %}
    <script src="{{ asset('bundles/myextension/assets/jquery-3.5.1.slim.min.js', 'asset') }}"></script>
{% endblock %}
```

**Attention:** If you need to test jQuery prior to the next major version, you must use the block `base_script_jquery` inside `platform/src/Storefront/Resources/views/storefront/base.html.twig`, instead.
The block `base_script_jquery` will be moved to `layout/meta.html.twig` with the next major version. However, the purpose of the block remains the same:

```html
{% sw_extends '@Storefront/storefront/base.html.twig' %}

{% block base_script_jquery %}
    <script src="{{ asset('bundles/myextension/assets/jquery-3.5.1.slim.min.js', 'asset') }}"></script>
{% endblock %}
```
* The function `translatedTypes` in `src/app/component/rule/sw-condition-type-select/index.js` is removed. Use `translatedLabel` property of conditions.

## Storefront bundled JavaScript

With the major version 6.5, we've updated to webpack v5 and Bootstrap to v5. Because of these changes to the JavaScript bundling and vendor libraries,
previously bundled JavaScript which was created with Shopware 6.4.x is not compatible with Shopware 6.5.

Please re-build your bundled JavaScript inside `<YourPlugin>/src/Resources/app/storefront/dist/storefront/js/<your-plugin>.js` using `bin/build-storefront.sh`

## CSRF Removal in Favor of SameSite

We removed the CSRF protection in favor of SameSite strategy which is already implemented in shopware6.

If you changed or added forms with csrf protection, you have to remove all calls to the twig function `sw_csrf` and every input (hidden) field which holds the csrf token.
You can no longer use the JavaScript properties `window.csrf` or `window.storeApiProxyToken`.
The Route to `frontend.csrf.generateToken` will no longer work.

You don't have to implement any additional post request protection, as the SameSite strategy is already in place.

## Node requirements increased

Increased Node version to 18 and NPM to version 8 or 9.

## Removal of the  `/_proxy/store-api`-API route

The `store-api` proxy route was removed. Please use the store-api directly.
If that is not possible use a custom controller, that calls the `StoreApiRoute` internally.
The `StoreApiClient` class from `storefront/src/service/store-api-client.service.js` was also removed, as that class relied on the proxy route.

To access the cart via storefront javascript, you can use the `/checkout/cart.json` route.

## Storefront theme asset refactoring

In previous Shopware versions the theme assets has been copied to both folders `bundles/[theme-name]/file.png` and `theme/[id]/file.png`.
This was needed to be able to link the asset in the Storefront as the theme asset doesn't include the theme path prefix.

To improve the performance of `theme:compile` and to reduce the confusion of the usage of assets we copy the files only to `theme/[id]`.

To use the updated asset package,
replace your current `{{ asset('logo.png', '@ThemeName') }}` with `{{ asset('logo.png', 'theme') }}`

## Moved and changed the `ThemeCompilerEnrichScssVariablesEvent`
We moved the event `ThemeCompilerEnrichScssVariablesEvent` from `\Shopware\Storefront\Event\ThemeCompilerEnrichScssVariablesEvent` to `\Shopware\Storefront\Theme\Event\ThemeCompilerEnrichScssVariablesEvent`.
Please use the new event now.

## Change the script tag location in the default Storefront theme

All `base_body_script` child blocks and their `<script>` tags are moved from `Resources/views/storefront/base.html.twig` to `Resources/views/storefront/layout/meta.html.twig`. The block `base_body_script` itself remains in the `base.html.twig` template to offer the option to inject scripts before the `</body>` tag if desired.

The scripts got a `defer` attribute to allow downloading the script file while the HTML document is still loading. The script execution happens after the document is parsed.

Example for a `<script>` extension in the template:

### Before

```html
{% sw_extends '@Storefront/storefront/base.html.twig' %}

{% block base_script_router %}
{{ parent() }}

<script type="text/javascript" src="extra-script.js"></script>
{% endblock %}
```

### After

```html
{% sw_extends '@Storefront/storefront/layout/meta.html.twig' %}

{% block layout_head_javascript_router %}
{{ parent() }}

<script type="text/javascript" src="extra-script.js"></script>
{% endblock %}
```

## Overwrite or extend line item templates:

If you're extending line item templates inside the cart, OffCanvas or other areas, you need to use the line item base template `Resources/views/storefront/component/line-item/line-item.html.twig`
and extend from one of the template files inside the `Resources/views/storefront/component/line-item/types/` directory.

For example, You extend the line item's information about product variants with additional content.

### Before
```twig
{# YourExtension/src/Resources/views/storefront/page/checkout/checkout-item.html.twig #}

{% sw_extends '@Storefront/storefront/page/checkout/checkout-item.html.twig' %}

{% block page_checkout_item_info_variant_characteristics %}
    {{ parent() }}
    <div>My extra content</div>
{% endblock %}
```

### After
```twig
{# YourExtension/src/Resources/views/storefront/component/line-item/type/product.html.twig #}

{% sw_extends '@Storefront/storefront/component/line-item/type/product.html.twig' %}

{% block component_line_item_type_product_variant_characteristics %}
    {{ parent() }}
    <div>My extra content</div>
{% endblock %}
```

Since the new `line-item.html.twig` is used throughout multiple areas, the template extension above will take effect for product line items
in all areas. Depending on your use case, you might want to restrict this to more specific areas. You have the possibility to check the
current `displayMode` to determine if the line item is shown inside the OffCanvas for example. Previously, the OffCanvas line items had
an individual template. You can now use the same `line-item.html.twig` template as for regular line items.

### Before
```twig
{# YourExtension/src/Resources/views/storefront/component/checkout/offcanvas-item.html.twig #}

{% sw_extends '@Storefront/storefront/component/checkout/offcanvas-item.html.twig' %}

{% block cart_item_variant_characteristics %}
    {{ parent() }}
    <div>My extra content</div>
{% endblock %}
```

### After
```twig
{# YourExtension/src/Resources/views/storefront/component/line-item/type/product.html.twig #}

{% sw_extends '@Storefront/storefront/component/line-item/type/product.html.twig' %}

{% block component_line_item_type_product_variant_characteristics %}
    {{ parent() }}

    {# Only show content when line item is inside offcanvas #}
    {% if displayMode === 'offcanvas' %}
        <div>My extra content</div>
    {% endif %}
{% endblock %}
```

You can narrow down this even more by checking for the `controllerAction` and render your changes only in desired actions.
The dedicated `confirm-item.html.twig` in the example below no longer exists. You can use `line-item.html.twig` as well.

### Before
```twig
{# YourExtension/src/Resources/views/storefront/page/checkout/confirm/confirm-item.html.twig #}

{% sw_extends '@Storefront/storefront/page/checkout/confirm/confirm-item.html.twig' %}

{% block cart_item_variant_characteristics %}
    {{ parent() }}
    <div>My extra content</div>
{% endblock %}
```

### After
```twig
{# YourExtension/src/Resources/views/storefront/component/line-item/type/product.html.twig #}

{% sw_extends '@Storefront/storefront/component/line-item/type/product.html.twig' %}

{% block component_line_item_type_product_variant_characteristics %}
    {{ parent() }}

    {# Only show content on the confirm page #}
    {% if controllerAction === 'confirmPage' %}
        <div>My extra content</div>
    {% endif %}
{% endblock %}
```

## Atomic theme compilation

To allow atomic theme compilations, a seeding mechanism for `AbstractThemePathBuilder` was added.
Whenever a theme is compiled, a new seed is generated and passed to the `generateNewPath()` method, to generate a new theme path with that seed.
After the theme was compiled successfully the `saveSeed()` method is called to that seed, after that subsequent calls to the `assemblePath()` method, must use the newly saved seed for the path generation.

Additionally, the default implementation for `\Shopware\Storefront\Theme\AbstractThemePathBuilder` was changed from `\Shopware\Storefront\Theme\MD5ThemePathBuilder` to `\Shopware\Storefront\Theme\SeedingThemePathBuilder`.

Obsolete compiled theme files are now deleted with a delay, whenever a new theme compilation created new files.
The delay time can be configured in the `shopware.yaml` file with the new `storefront.theme.file_delete_delay` option, by default it is set to 900 seconds (15 min), if the old theme files should be deleted immediately you can set the value to 0.

For more details refer to the corresponding [ADR](adr/2023-01-10-atomic-theme-compilation.md).

## Selector to open an ajax modal
The JavaScript plugin `AjaxModal` is able to open a Bootstrap modal and fetching content via ajax.
This is currently done by using the known Bootstrap selector for opening modals `[data-bs-toggle="modal"]` and an additional `[data-url]`.
The corresponding modal HTML isn't existing upfront and will be created by `AjaxModal` internally by using the `.js-pseudo-modal-template` template.
However, Bootstrap v5 needs a `data-bs-target="*"` upfront which points to the desired HTML of a modal. Otherwise, it throws a JavaScript error because the Modal's DOM can't be found.
The `AjaxModal` itself works regardless of the error.

Because we don't want to enforce to have an additional `data-bs-target="*"` selector everywhere and want to keep the general workflow using `AjaxModal`, we change the
selector, which is initializing the `AjaxModal` plugin, to `[data-ajax-modal][data-url]` to not interfere with the Bootstrap default modal.
`AjaxModal` will do all modal related tasks programmatically and doesn't rely on Bootstraps data-attribute API.

### Before
```html
<a data-bs-toggle="modal" data-url="/my/route" href="/my/route">Open Ajax Modal</a>
```

### After
```html
<a data-ajax-modal="true" data-url="/my/route" href="/my/route">Open Ajax Modal</a>
```

## Possible empty response in checkout info route

The route `/widgets/checkout/info` will now return an empty response with HTTP status code `204 - No Content`, as long as the cart is empty, instead of loading the page and responding with a rendered template.

If you call that route manually in your extensions, please ensure to handle the `204` status code correctly.

Additionally, as the whole info widget pagelet will not be loaded anymore for empty carts, your event subscriber or app scripts for that page also won't be executed anymore for empty carts.

## Storefront OffCanvas requires different HTML:

The OffCanvas module of the Storefront (`src/plugin/offcanvas/ajax-offcanvas.plugin`) was changed to use the Bootstrap v5 OffCanvas component in the background.
If you pass a string of HTML manually to method `OffCanvas.open()`, you need to adjust your markup according to Bootstrap v5 in order to display the close button and content/body.

See: https://getbootstrap.com/docs/5.1/components/offcanvas/

### Before
```js
const offCanvasContent = `
<button class="btn btn-light offcanvas-close js-offcanvas-close btn-block sticky-top">
    Close
</button>
<div class="offcanvas-content-container">
    Content
</div>
`;

OffCanvas.open(offCanvasContent);
```

### After
```js
// OffCanvas now needs additional `offcanvas-header`
// Content class `offcanvas-content-container` is now `offcanvas-body`
const offCanvasContent = `
<div class="offcanvas-header p-0">
    <button class="btn btn-light offcanvas-close js-offcanvas-close btn-block sticky-top">
        Close
    </button>
</div>
<div class="offcanvas-body">
    Content
</div>
`;

// No need for changes in general usage!
OffCanvas.open(offCanvasContent);
```

# Extensions

## Removed prefix from app module menu entries
As for now, we've prefixed your app's module label with the app name to build navigation entries.
From 6.5 on, this prefixing will be removed.

```diff
const entry = {
    id: `app-${app.name}-${appModule.name}`,
    label: {
        translated: true,
-       label: `${appLabel} - ${moduleLabel}`,
+       label: moduleLabel,
    },
    position: appModule.position,
    parent: appModule.parent,
    privilege: `app.${app.name}`,
};
```
**Example:** `Your App - Module Label` will become `Module Label` in Shopware's Administration menu.

**Important:** Please update your module label in your app's `manifest.xml` so it's clearly identifiable by your users.
Keep in mind that using a generic label could lead to cases where multiple apps use the same or similar module labels.

## New `executeComposerCommands` option for plugins

If your plugin provides 3rd party dependencies, override the `executeComposerCommands` method in your plugin base class
and return true.
Now on plugin installation and update of the plugin a `composer require` of your plugin will also be executed,
which installs your dependencies to the root vendor directory of Shopware.
On plugin uninstallation a `composer remove` of your plugin will be executed,
which will also remove all your dependencies.
If you ship dependencies with your plugins within the plugin ZIP file, you should now consider using this config instead.

## Deprecated manifest-1.0.xsd

With the upcoming major release, we're going to release a new XML-schema for Shopware Apps.
In the new schema we remove two deprecations from the existing schema.

1. attribute `parent` for element `module` will be required.

   Please make sure that every of your admin modules has this attribute set
   like described in [our documentation](https://developer.shopware.com/docs/guides/plugins/apps/administration/add-custom-modules)
2. attribute `openNewTab` for element `action-button` will be removed.

   Make sure to remove the attribute `openNewTab` from your `action-button` elements in your `manifest.xml` and use ActionButtonResponses as described in our [documentation](https://developer.shopware.com/docs/guides/plugins/apps/administration/add-custom-action-button) instead.
3. Deprecation of `manifest-1.0.xsd`

   Update the `xsi:noNamespaceSchemaLocation` attribute of your `manifest` root element to `https://raw.githubusercontent.com/shopware/platform/trunk/src/Core/Framework/App/Manifest/Schema/manifest-2.0.xsd`
