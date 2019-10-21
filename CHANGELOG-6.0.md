CHANGELOG for 6.0.x
===================

This changelog references the relevant changes (bug and security fixes) done
in 6.0 minor and early access versions.

To get the diff for a specific change, go to https://github.com/shopware/platform/commit/XXX where XXX is the change hash
To get the diff between two versions, go to https://github.com/shopware/platform/compare/v6.0.0+dp1...v6.0.0+ea1

### 6.0.0 EA1 (2019-07-17)

**Additions / Changes**

* Added `JoinBuilderInterface` and moved join logic from `FieldResolver` into `JoinBuilder`
* Added getJoinBuilder to `FieldResolverInterface`
* Fixed Twig template loading for the theme system. Twig files from themes will only be loaded if the theme is active
 for the requested sales channel.
* Added `active` column to `theme` entity.
* Improved theme lifecycle handling. Themes will be set to inactive if deactivated/uninstalled. Config will be reloaded
  when a theme is updated. Themes will automatically be recompiled if the plugin is updated.
* Improved loading/refresh of theme.json. You can now change the theme.json and use `bin/console theme:refresh` to
  reload the configuration.
[View all changes from v6.0.0+dp1...v6.0.0+ea1](https://github.com/shopware/platform/compare/v6.0.0+dp1...v6.0.0+ea1)
* Added Twig filters `sw_encode_url` and `sw_encode_media_url` to Storefront. Contrary to Twig's `url_encode` filter it encodes every segment of the path rather than the whole url string.
You can use them with every URL in your templates
```
{# results in http://your.domain:8080/path%20to/file%20with%20whitspace-and%28brackets%29.png #}
<img src="{{ "http://your.domain:8080/path to/file with whitspace-and(brackets).png" | sw_encode_url }}"

{# encodes the url of your media entity #}
<img src="{{ yourStorefrontMediaObject | sw_encode_media_url }}
```
* We added the `$path` property to the `WriteCommandInterface`. With this you can track your commands initial position in the request.
This can be useful when validate your commands in `PreWriteValidateEvent`s when the commandqueue is already in write order.

### 6.0.0 EA2

**Additions / Changes**

* Administration
    * Moved the global state of the module `sw-cms` to VueX
    * Renamed `sw-many-to-many-select` to `sw-entity-many-to-many-select`
    * Renamed `sw-tag-field-new` to `sw-entity-tag-select`
    * Added `sw-select-base`, `sw-select-result`, `sw-select-result-list` and `sw-select-selection-list` as base components for select fields
    * Changed select components in path `administration/src/app/component/form/select` to operate with v-model
    * Deprecated `sw-tag-field` use `sw-entity-tag-select` instead
    * Deprecated `sw-select` use `sw-multi-select` or `sw-single-select` instead
    * Deprecated `sw-select-option` use `sw-result-option` instead
    * Moved the `sw-cms` from `State.getStore()` to `Repository` and added clientsided data resolver
    * Added translations for the `sw-cms` module
    * Added `getComponentHelper` to global `Shopware` object
    * Added async loading of plugins
    * Added seperation of login and application boot
    * Replaced vanilla-colorpicker dependency with custom-build vuejs colorpicker
    * `EntityCollection.filter` returns a new `EntityCollection` object instead of a native array 
    * Added Sections which support sidebars to the `sw-cms`
    * Navigation sidebar is now a globally expandable & collapsable with `this.$store.commit('adminMenu/collapseSidebar)` and `this.$store.commit('adminMenu/expandSidebar)`
    * Added Services functions to Shopware object for easier access
    * Added Context to Shopware object for easier access
* Core
    * Added DAL support for multi primary keys.
    * Added API endpoints for translation definitions
    * Added new event `\Shopware\Core\Content\Category\Event\NavigationLoadedEvent` which dispatched after a sales channel navigation loaded
    * Added restriction to storefront API to prevent filtering, sorting, aggregating and association loading of ReadProtected fields/entities
    * Added `\Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria::addAssociations` which allows to add multiple association paths
    * Added `\Shopware\Core\Framework\DataAbstractionLayer\Field\StateMachineStateField`
    * Added generic `\Shopware\Core\System\StateMachine\Api\StateMachineActionController`
    * Changed field `stateId` from `FkField` to `StateMachineStateField` in `OrderDefinition` and `OrderTransactionDefinition`
    * Changed parameter of `\Shopware\Core\System\StateMachine\StateMachineRegistry::transition`. `\Shopware\Core\System\StateMachine\Transition` is now expected.
    * Changed behaviour of `\Shopware\Core\System\StateMachine\StateMachineRegistry::transition` to now expect the action name instead of the toStateName
    * Changed signature of `\Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria::addAssociation`
      The second parameter `$criteria` has been removed. Use `\Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria::getAssociation` instead.
    * Changed the name of `core.<locale>.json` to `messages.<locale>.json` and changed to new base file.
    * Changed name of property in CurrencyDefinition from `isDefault` to `isSystemDefault`
    * Added RouteScopes as required Annotation for all Routes
    * Added new function `\Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerInterface::partial` to index partially in time limited requests
    * Added `\Shopware\Core\Framework\Migration\InheritanceUpdaterTrait` to update entity schema for inherited associations
    * Changed default enqueue transport from enqueue/fs to enqueue/dbal
    * Made the service `\Shopware\Core\System\SystemConfig\SystemConfigService` public
    * Removed `Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\ValueCount` use `Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\Terms`instead
    * Removed `Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Value` use `Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\Terms`instead
    * Refactored DAL aggregation system, see `UPGRADE-6.1.md` for more details 
    * Changed `\Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult`, `...\Event\EntityWrittenEvent` `...\Event\EntityDeletedEvent` to make them serializable. See removals.
    * Added `\Shopware\Core\Framework\DataAbstractionLayer\EntityWrittenContainerEvent::getEventByEntityName` which returns all `EntityWrittenEvent`s for a given entity name.
    * Changed `Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence` to store primary keys in hex.
    * Added a new required parameter `DefinitionInstanceRegistry $definitionRegistry` to `Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\AbstractFieldSerializer`.
    * Refactored Kernel plugin loading into `\Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader`. By default the
    `\Shopware\Core\Framework\Plugin\KernelPluginLoader\DbalKernelPluginLoader` is used. The Kernel constructor changed, see `UPGRADE-6.1.md` for more details
    * Improved Plugin capabilities:
        - Plugin bundle class is automatically inserted into container with autoload and autowire
        - This allows setter injection in plugin bundle class
        - These services are now available in `\Shopware\Core\Framework\Plugin::activate` and `\Shopware\Core\Framework\Plugin::deactivate`
        - `\Shopware\Core\Framework\Plugin::deactivate` is now always called before `\Shopware\Core\Framework\Plugin::uninstall`
    * Renamed container service id `shopware.cache` to `cache.object` 
    * Added new function to `\Shopware\Core\Framework\Cache\CacheClearer`. Please use this service to invalidate or delete cache items.
    * We did some refactoring on how we use `WriteConstraintsViolationExceptions`.
    It's path `property` should now point to the object that is inspected by an validator while the `propertyPath` property in `WriteConstraint` objects should only point to the invalid property. 
    For more information read the updated "write command validation" article in the docs.
    * Added new function `\Shopware\Core\Framework\Migration\MigrationStep::registerIndexer`. This method registers an indexer that needs to run (after the update). See `\Shopware\Core\Migration\Migration1570684913ScheduleIndexer` for an example.
* Storefront
    * Changed the default storefront script path in `Bundle` to `Resources/dist/storefront/js`
    * Changed the name of `messages.<locale>.json` to `storefront.<locale>.json` and changed to **not** be a base file anymore.
    * Added `extractIdsToUpdate` to `Shopware\Storefront\Framework\Seo\SeoUrlRoute\SeoUrlRouteInterface`
    * Changed the behaviour of the SeoUrlIndexer to rebuild seo urls asynchronously in some cases where a single change to an entity can trigger huge amount if seo url changes.  
    * Added `\Shopware\Storefront\Framework\Cache\CacheWarmer\CacheRouteWarmerRegistry` which allows to warm up different http cache routes
    * Added `http:cache:warmup` console command to warm up the http cache.
    * Added new service `\Shopware\Storefront\Framework\Cache\CacheStore` which is used for the http cache
    * Added new .env variables `SHOPWARE_HTTP_CACHE_ENABLED` and `SHOPWARE_HTTP_DEFAULT_TTL` which configures the http cache.
    * Added `\Shopware\Storefront\Framework\Cache\ObjectCacheKeyFinder` which finds all entity cache keys in a none entity object.
    * Added twig helper function `seoUrl` that returns a seo url if possible, otherwise just calls `url`. 
    * Deprecated twig helper functions `productUrl` and `navigationUrl`, use `seoUrl` instead.

**Removals**

* Administration
    * Removed `sw-tag-multi-select`
    * Removed `sw-multi-select-option` use `sw-result-option` instead
    * Removed module export of `Shopware`
    * Removed plugin functionality in login
    * Removed `sw-single-select-option` use `sw-result-option` instead
    * Removed `Criteria.value` use `Criteria.terms` instead
    * Removed `Criteria.valueCount` use `Criteria.terms` instead
    * Removed `Criteria.addAssociationPath` use `Criteria.addAssociation` instead
* Core
    * Removed `\Shopware\Core\Checkout\Customer\SalesChannel\AddressService::getCountryList` function
    * Removed `\Shopware\Core\Framework\DataAbstractionLayer\Search\PaginationCriteria`
    * Removed `\Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria::addAssociationPath` use `\Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria::addAssociation` instead
    * Removed `\Shopware\Core\Checkout\Order\Api\OrderActionController` which is now replaced by the generic `\Shopware\Core\System\StateMachine\Api\StateMachineActionController`
    * Removed `\Shopware\Core\Checkout\Order\Api\OrderDeliveryActionController` which is now replaced by the generic `\Shopware\Core\System\StateMachine\Api\StateMachineActionController`
    * Removed `\Shopware\Core\Checkout\Order\Api\OrderTransactionActionController` which is now replaced by the generic `\Shopware\Core\System\StateMachine\Api\StateMachineActionController`
    * Removed `getDefinition` and the corresponding `definition` member from `\Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResults` and `...\Event\EntityWrittenEvent`.
    * Removed `\Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent::getWrittenDefinitions` as the definitions were removed from the event. 
    * Removed `\Shopware\Core\Framework\DataAbstractionLayer\EntityWrittenContainerEvent::getEventByDefinition`. Use `getEventByEntityName`.
    * Removed `\Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer::fieldHandlerRegistry`, `...\ListFieldSerializer::compositeHandler` and `...\PriceFieldSerializer::fieldHandlerRegistry` as they now use the `definitionRegistry` from their common `AbstractFieldSerializer` baseclass
    * Removed `\Shopware\Core\Kernel::getPlugins`, use `\Shopware\Core\Framework\Plugin\KernelPluginCollection` from the container instead
* Storefront
    * Removed `Shopware\Storefront\Framework\Seo\Entity\Field\CanonicalUrlField`, use the twig helper function `seoUrl` to get seo urls
    * Removed fields `isValid` and `autoIncrement` from `SeoUrlDefinition`


### 6.1.0

**Additions / Changes**

* Administration
* Core
    * Moved the seo module from the storefront into the core.
* Storefront
    * Changed `\Shopware\Storefront\Framework\Cache\CacheWarmer\CacheRouteWarmer` signatures
    * Moved most of the seo module into the core. Only storefront(route) specific logic/extensions remain

**Removals**

* Administration
* Core
    * When a sub entity is written or deleted, a written event is dispatched for the configured root entity. 
        - Example for mapping entities: Writing a `product_category` entity now also dispatches a `product.written` and `category.written` event
        - Example for simple sub entities: Writing a `product_price` entity now also dispatches a `product_category` event
        - Example for nested sub entities: Writing a `order_delivery_position` entity now also dispatches a `order_delivery.written` and a `order.written` event
* Storefront
    * Removed `\Shopware\Storefront\Framework\Cache\CacheWarmer\Navigation\NavigationRouteMessage`
    * Removed `\Shopware\Storefront\Framework\Cache\CacheWarmer\Product\ProductRouteMessage`
    * Removed `\Shopware\Storefront\Framework\Cache\CacheWarmer\CacheWarmerSender`
    * Removed `\Shopware\Storefront\Framework\Cache\CacheWarmer\IteratorMessage`
    * Removed `\Shopware\Storefront\Framework\Cache\CacheWarmer\IteratorMessageHandler`
