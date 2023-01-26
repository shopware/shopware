# 6.5.0.0
## Introduced in 6.4.17.0
* In the next major, the flow actions are not executed over the symfony events anymore, we'll remove the dependence from `EventSubscriberInterface` in `Shopware\Core\Content\Flow\Dispatching\Action\FlowAction`.
* In the next major, the flow actions are not executed via symfony events anymore, we'll remove the dependency from `EventSubscriberInterface` in `Shopware\Core\Content\Flow\Dispatching\Action\FlowAction`.
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
* The interface `Shopware\Core\System\Snippet\Files\SnippetFileInterface` is deprecated, please use `Shopware\Core\System\Snippet\Files\AbstractSnippetFile` instead.

## Remove old database migration trigger logic
The `addForwardTrigger()`, `addBackwardTrigger()` and `addTrigger()` methods of the `MigrationStep` class were removed, use `createTrigger()` instead.
Do not rely on the state of already executed migrations in your database triggers anymore!
Additionally, the `@MIGRATION_{migration}_IS_ACTIVE` DB connection variables are not set at kernel boot anymore.

## Removal of `\Shopware\Core\Framework\Event\FlowEvent`
We removed `\Shopware\Core\Framework\Event\FlowEvent`, since Flow Actions are not executed via symfony's event system anymore.
You should implement the `handleFlow()` method in your `FlowAction` and tag your actions as `flow.action`.

## Internal Migrations
All DB migration steps are now considered `@internal`, as they never should be extended or adjusted afterwards.

## Introduced in 6.4.16.0
## Removal of `/api/_action/database`
The `/api/_action/database` endpoint was removed, this means the following routes are not available anymore:
* `POST /api/_action/database/sync-migration`
* `POST /api/_action/database/migrate`
* `POST /api/_action/database/migrate-destructive`

The migrations can not be executed over the API anymore. Database migrations should be only executed over the CLI.

## Introduced in 6.4.15.0
## Deprecated the `OpenApiPathsEvent`:
* Move the schema described by your `@OpenApi` / `@OA` annotations to json files.
* New the openapi specification is now loaded from `$bundlePath/Resources/Schema/`.
* For an examples look at `src/Core/Framework/Api/ApiDefinition/Generator/Schema`.
## Removed `DatabaseInitializer`

Removed class `\Shopware\Core\Maintenance\System\Service\DatabaseInitializer`, use `SetupDatabaseAdapter` instead.

## Removed `JwtCertificateService`

Removed class `\Shopware\Recovery\Common\Service\JwtCertificateService`, use `JwtCertificateGenerator` instead.

## Introduced in 6.4.14.0
## Removal of old icons:
* Replace any old icon your integration uses with its successor. A mapping can be found here `src/Administration/Resources/app/administration/src/app/component/base/sw-icon/legacy-icon-mapping.js`.
* The object keys of the json file are the legacy icons. The values the replacement.
* In the next major the icons have will have no space around them by default. This could eventually lead to bigger looking icons in some places. If this is the case you need to adjust the styling with CSS so that it matches your wanted look.

### Example:
Before:

```html
<sw-icon name="default-object-image"/>
```

After:
```html
<sw-icon name="regular-image"/>
```
## Extending `StringTemplateRenderer`

The class `StringTemplateRenderer` should not be extended and will become `final`.

## Introduced in 6.4.13.0
## Moved and changed the `ThemeCompilerEnrichScssVariablesEvent`
We moved the event `ThemeCompilerEnrichScssVariablesEvent` from `\Shopware\Storefront\Event\ThemeCompilerEnrichScssVariablesEvent` to `\Shopware\Storefront\Theme\Event\ThemeCompilerEnrichScssVariablesEvent`.
Please use the new event now.

## Method `pluginActivate` in `PluginLifecycleSubscriber` will be exchanged with new method `pluginPostActivate`
We exchanged the method `pluginActivate` in `PluginLifecycleSubscriber` and will now use the `pluginPostActivate` with the `PluginPostActivateEvent`.
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
## Removal of the  `/_proxy/store-api`-API route

The `store-api` proxy route was removed. Please use the store-api directly.
If that is not possible use a custom controller, that calls the `StoreApiRoute` internally.
The `StoreApiClient` class from `storefront/src/service/store-api-client.service.js` was also removed, as that class relied on the proxy route.
## Removed repository decorators
Removed the following repository decorators:
* `MediaRepositoryDecorator`
* `MediaThumbnailRepositoryDecorator`
* `MediaFolderRepositoryDecorator`
* `PaymentMethodRepositoryDecorator`

If you used one of the repository and type hint against this specific classes, you have to change your type hints to `EntityRepository`:

### Before
```php
private MediaRepositoryDecorator $mediaRepository;
```

### After
```php
private EntityRepositoryInterface $mediaRepository;
```

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

## `@internal` entity repositories
We removed the `EntityRepositoryInterface` & `SalesChannelRepositoryInterface` classes and declared the `EntityRepository` & `SalesChannelRepository` as final. Therefor if you implemented an own repository class for your entities, you have to remove this now. To modify the repository calls you can use one of the following events:
* `BeforeDeleteEvent`: Allows an access point for before and after deleting the entity
* `EntitySearchedEvent`: Allows access points to the criteria for search and search-ids
* `PreWriteValidationEvent`/`PostWriteValidationEvent`: Allows access points before and after the entity written
* `SalesChannelProcessCriteriaEvent`: Allows access to the criteria before the entity is search within a sales channel scope

Additionally, you have to change your type hints from `EntityRepositoryInterface` & `SalesChannelRepositoryInterface` to `EntityRepository` or `SalesChannelRepository`:

## Introduced in 6.4.12.0
## Deprecations in `Shopware\Core\Framework\Store\Services\StoreAppLifecycleService`
The class `StoreAppLifecycleService` has been marked as internal.

We also removed the `StoreAppLifecycleService::getAppIdByName()` method.

## Removal of `Shopware\Core\Framework\Store\Exception\ExtensionRequiresNewPrivilegesException`
We removed the `ExtensionRequiresNewPrivilegesException` exception.
Will be replaced with the internal `ExtensionUpdateRequiresConsentAffirmationException` exception to have a more generic one.
## Overwrite or extend line item templates:

If you are extending line item templates inside the cart, OffCanvas or other areas, you need to use the line item base template `Resources/views/storefront/component/line-item/line-item.html.twig`
and extend from one of the template files inside the `Resources/views/storefront/component/line-item/types/` directory.

For example: You extend the line item's information about product variants with additional content.

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
## Removal of `Feature::triggerDeprecated()`

The method `Feature::triggerDeprecated()` was removed, use `Feature::triggerDeprecationOrThrow()` instead.
## Removal of the `psalm` dependency

The platform does not rely on `psalm` for static analysis anymore, but solely uses `phpstan` for that purpose.
Therefore, the `psalm` dev-dependency was removed. 
If you used the dev-dependency from platform in your project, please install the `psalm` package directly into your project.

## Introduced in 6.4.11.0
## ArrayEntity::getVars():
* The `ArrayEntity::getVars()` has been changed so that the `data` property is no longer in the payload but applied to the `root` level.
  * This change affects all entity definitions that do not have their own entity class defined.
  * The API routes should not be affected, because they did not work with an ArrayEntity before the change, so no before/after payload can be shown.
  * before
  ```php 
  $entity = new ArrayEntity(['foo' => 'bar']);
  assert($entity->getVars(), ['data' => ['foo' => 'bar'], 'foo' => 'bar']);
  ```

  * after
  ```json 
  $entity = new ArrayEntity(['foo' => 'bar']);
  assert($entity->getVars(), ['foo' => 'bar']);
  ```
## Skipping of the cart calculation if the cart is empty

If the cart is empty the cart calculation will be skipped.
This means that all `\Shopware\Core\Checkout\Cart\CartProcessorInterface` and `\Shopware\Core\Checkout\Cart\CartDataCollectorInterface` will not be executed anymore if the cart is empty.
## Possible empty response in checkout info route

The route `/widgets/checkout/info` will now return an empty response with HTTP status code `204 - No Content`, as long as the cart is empty, instead of loading the page and responding with a rendered template.

If you call that route manually in your extensions, please ensure to handle the `204` status code correctly.

Additionally, as the whole info widget pagelet will not be loaded anymore for empty carts, your event subscriber or app scripts for that page also won't be executed anymore for empty carts.
## New Profiling pattern
Due to a new and better profiling pattern we removed the following services:
* `\Shopware\Core\Profiling\Checkout\SalesChannelContextServiceProfiler`
* `\Shopware\Core\Profiling\Entity\EntityAggregatorProfiler`
* `\Shopware\Core\Profiling\Entity\EntitySearcherProfiler`
* `\Shopware\Core\Profiling\Entity\EntityReaderProfiler`

You can now use the `Profiler::trace()` function to add custom traces directly from your services.
## Refactoring of Number Ranges

We refactored the number range handling, to be faster and allow different storages to be used.
### Removal of `IncrementStorageInterface`

We removed the `Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementStorageInterface`.
If you have implemented a custom increment storage please use the abstract class `Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\AbstractIncrementStorage`.
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
### Removal of `ValueGeneratorPatternInterface`

We removed the `Shopware\Core\System\NumberRange\ValueGenerator\Pattern\ValueGeneratorPatternInterface`.
If you have implemented a custom value pattern please use the abstract class `Shopware\Core\System\NumberRange\ValueGenerator\Pattern\AbstractValueGenerator`.

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

## Introduced in 6.4.10.0
* Deprecated function `logBusinessEvent` at `src/Core/Framework/Log/LoggingService.php`.
* Deprecated `src/Core/Framework/Log/LogAwareBusinessEventInterface.php` use `LogAware` instead.
## Removal of `\Shopware\Core\Framework\Adapter\Twig\EntityTemplateLoader::clearInternalCache()`

We removed `\Shopware\Core\Framework\Adapter\Twig\EntityTemplateLoader::clearInternalCache()`, use `reset()` instead.

## Introduced in 6.4.9.0
Bootstrap v5 introduces breaking changes in HTML, (S)CSS and JavaScript.
Below you can find a migration overview of the effected areas in the Shopware platform.
Please consider that we cannot provide code migration examples for every possible scenario of a UI-Framework like Bootstrap.
You can find a full migration guide on the official Bootstrap website: [Migrating to v5](https://getbootstrap.com/docs/5.1/migration)

## HTML/Twig

The Update to Bootstrap v5 often contains the renaming of attributes and classes. Those need to be replaced.
However, all Twig blocks remain untouched so all template extensions will take effect.

### Rename attributes and classes

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

### Replace .btn-block class with .d-grid wrapper

#### Before

```html
<a href="#" class="btn btn-block">Default button</a>
```

#### After

```html
<div class="d-grid">
    <a href="#" class="btn">Default button</a>
</div>
```

### Remove .input-group-append wrapper inside .input-group

#### Before

```html
<div class="input-group">
    <input type="text" class="form-control">
    <div class="input-group-append">
        <button type="submit" class="btn">Submit</button>
    </div>
</div>
```

#### After

```html
<div class="input-group">
    <input type="text" class="form-control">
    <button type="submit" class="btn">Submit</button>
</div>
```

## SCSS

Please consider that the classes documented in "HTML/Twig" must also be replaced inside SCSS.

* Replace all mixin usages of `media-breakpoint-down()` with the current breakpoint, instead of the next breakpoint:
    * Replace `media-breakpoint-down(xs)` with `media-breakpoint-down(sm)`
    * Replace `media-breakpoint-down(sm)` with `media-breakpoint-down(md)`
    * Replace `media-breakpoint-down(md)` with `media-breakpoint-down(lg)`
    * Replace `media-breakpoint-down(lg)` with `media-breakpoint-down(xl)`
    * Replace `media-breakpoint-down(xl)` with `media-breakpoint-down(xxl)`
* Replace `$custom-select-*` variable with `$form-select-*`

## JavaScript/jQuery

With the update to Bootstrap v5, the jQuery dependency will be removed from the shopware platform.
We strongly recommend migrating jQuery implementations to Vanilla JavaScript.

### Initializing Bootstrap JavaScript plugins

#### Before

```js
// Previously Bootstrap plugins were initialized on jQuery elements
const collapse = DomAccess.querySelector('.collapse');
$(collapse).collapse('toggle');
```

#### After

```js
// With Bootstrap v5 the Collapse plugin is instantiated and takes a native HTML element as a parameter
const collapse = DomAccess.querySelector('.collapse');
new bootstrap.Collapse(collapse, {
    toggle: true,
});
```

### Subscribing to Bootstrap JavaScript events

#### Before

```js
// Previously Bootstrap events were subscribed using the jQuery `on()` method
const collapse = DomAccess.querySelector('.collapse');
$(collapse).on('show.bs.collapse', this._myMethod.bind(this));
$(collapse).on('hide.bs.collapse', this._myMethod.bind(this));
```

#### After

```js
// With Bootstrap v5 a native event listener is being used
const collapse = DomAccess.querySelector('.collapse');
collapse.addEventListener('show.bs.collapse', this._myMethod.bind(this));
collapse.addEventListener('hide.bs.collapse', this._myMethod.bind(this));
```

### Still need jQuery?

In case you still need jQuery, you can add it to your own app or theme.
This is the recommended method for all apps/themes which do not have control over the Shopware environment in which they are running in.

* Extend the file `platform/src/Storefront/Resources/views/storefront/layout/meta.html.twig`.
* Use the block `layout_head_javascript_jquery` to add a `<script>` tag containing jQuery. **Only use this block to add jQuery**.
* This block is not deprecated and can be used in the long term beyond the next major version of shopware.
* Do **not** use the `{{ parent() }}` call. This prevents multiple usages of jQuery. Even if multiple other plugins/apps use this method, the jQuery script will only be added once.
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
```

## Introduced in 6.4.8.0
The whole namespace `Shopware\Core\Framework\Changelog` was marked `@internal` and is no longer part of the BC-Promise. Please move to a different changelog generator vendor.

