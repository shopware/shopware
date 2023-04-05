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
The reasons for those changes are outlined in this [ADR](../../adr/api/2022-02-09-controller-configuration-route-defaults.md) and for a lot of former annotations this change was already done previously.
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
* If you don't want to use a CDN for jQuery, [download jQuery from the official website](https://releases.jquery.com/jquery/) (jQuery Core 3.5.1 - slim minified) and add it to `MyExtension/src/Resources/app/storefront/src/assets/jquery-3.5.1.slim.min.js`
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
replace your current `{{ asset('logo.png', '@ThemeName') }}` with `{{ asset('logo.png', 'theme'') }}`

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

For more details refer to the corresponding [ADR](../../adr/storefront/2023-01-10-atomic-theme-compilation.md).

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
