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
    * Added a new component `sw-many-to-many-assignment-card` which can be used to display many to many associations within a paginated grid
    * The `sw-tree` component now emits the `editing-end` when the user finished adding new items. Eventdata is an object with `parentId` property 
    * `sw-tree`'s `drag-end` event now emits information about the old and new parentId of the dragged element.
    * The changeset generator now ommits write protected fields
    * We added `fromCollection` and `fromCriteria` methods to criteria/collections to deep copy them if needed
    * Due to the redesign of the cms blocks and elements you can now translate the label of your blocks and elements
    * The Layouts which can be assigned under settings > basic information > Shop Pages now have to be of the type `shop page`
    * You can now assign a 404 error page layout in settings > basic information > Shop Pages which will be rolled out in a 404 not found error.
    * Moved all rule specific components to `src/app/components/rule`
    * Added error handling for multiple errors per request
    * Replaced `repository.sync` with a request against the `sync` endpoint. The former behavior of `sync` ist now available as `repository.saveAll`
    * We splitted the component `sw-sales-channel-modal` up into two additional components `sw-sales-channel-modal-detail` and `sw-sales-channel-modal-grid`
    * `sw-sales-channel-detail-base` got refactored based on a new design, the domain management was moved to a new component
    * Created a new component `sw-sales-channel-detail-domains` which handles the domain management of a sales channel now
    * `sw-entity-single-select` fires a new event called `option-select` which provides the selected entity and  as the payload
    * you can now set a sales channel in a maintenance mode. You can choose the layout or use our default layout. You can also use the ip-whitelist to bypass the maintenance mode. The Imprint and Privacy Policy from Settings > Basic Information are linked in the maintenance page footer if you specified a layout.
    * Added mailer settings in the settings page
    * Added mailer settings in the first run wizard
    * Now snippets are retrieved asynchronously to improve performance

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
    * Added new function to `\Shopware\Core\Framework\Adapter\Cache\CacheClearer`. Please use this service to invalidate or delete cache items.
    * We did some refactoring on how we use `WriteConstraintsViolationExceptions`.
    It's path `property` should now point to the object that is inspected by an validator while the `propertyPath` property in `WriteConstraint` objects should only point to the invalid property. 
    For more information read the updated "write command validation" article in the docs.
    * Added new function `\Shopware\Core\Framework\Migration\MigrationStep::registerIndexer`. This method registers an indexer that needs to run (after the update). See `\Shopware\Core\Migration\Migration1570684913ScheduleIndexer` for an example.
    * Added the `extractInheritableAttributes()` function to the `\Shopware\Core\Framework\Routing\RequestTransformerInterface`
    
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
    * Added ErrorPage, ErrorpageLoader and ErrorPageLoaderEvent which is used in the `ErrorController` to load the CMS error layout if a 404 layout is assigned.
    * Added an option to disable eslint for storefront:build
    * Removed abandoned TwigExtensions in favor of  Twig Core Extra extensions

**Removals**

* Administration
    * Removed `sw-tag-multi-select`
    * Removed `sw-multi-select-option` use `sw-result-option` instead
    * Removed module export of `Shopware`
    * Removed plugin functionality in login
    * Removed direct component registration in modules
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
    * Moved the sw-product-maintain-currencies-modal to sw-maintain-currencies-modal.
    * Seperate the Shopware context to App-Context and Api-Context
    * Rename old `State` to `StateDeprecated`
    * Make vuex store initially available in global Shopware object `Shopware.State`
    * Move context to the Store
    * Make vuex store initially available
    * Moved `Resources/administration` directory to `Resources/app/administration`
    * Add cache busting to the administration script and style files through last modified timestamp in combination with the filesize
    * Double opt in for registrations and guests is now configurable in the settings at the Login / Registration module
    * Added component `sw-entitiy-multi-id-select` which can be used to select entities but only emit their ids
        * The v-model is bound to `change` event and sets the `ids` property
        * exposes the same slots as any `select` component
    * Added component `sw-arrow-field` which can be used to wrap components in a breadcrumb like visualization
        * It takes two props `primary` and `secondary` which are color keys for the arrow's background and border color
        * Additional content can be placed in the default slot of the component
    * `sw-tagged-field` now works with `event.key` instead of `event.keycode`
    * `sw-tagged-field` v-model event changed to `change` instead of `input` an does not mutate the original prop anymore
    * Removed component `sw-condition-value`
    * Added component `sw-condition-type-select` 
    * Removed `config` property from `sw-condition-tree`.
    * Refactored
    * Replaced Store based datahandling with repository based datahandling in `sw-settings-rule`, `sw-product-stream` modules and rule/product stream specific components
    * Removed client side validation for rules and product streams
    * Added APi validation for rules and product streams
    * Split `sw-product-stream-filter` component into smaler subcomponents `sw-product-stream-field-select` and `sw-product-stream-value`
    * Removed `sw-product-stream-create` page
    * Removed `sw-settings-rule-create` page
    * Added event `selection-change` to `sw-data-grid`. The event is fired whenever the selection in the grid changes including pagination and delete requests.
      * The event emits two values: the actual selection and the selection count of the `sw-data-grid`.
      * `change-selection` should be preferred over `select-item` if you are interested in the selection itself and not in a specific item that was selected.
    * Added custom fields to categories 
    * `v-popover` directive accepts a config object now which allows to activate / deactivate the directive on the fly, define the target element and if the popover element should be resized to the size of the origin element 
    * Updated Symfony Dependencies to version `4.4.0`.    
    * Added cms block `form`
    * Added component `sw-select-number-field` for select options with numeric values
    * Added installation opportunity of Migration-Plugin into FirstRunWizard
    * Added `sw-order-state-change-modal-assign-mail-template` component inside `sw-order-state-change-modal`
    * Moved `sw-order-document-card` in `sw-order-state-change-modal` into seperate component
    * integrated repository based data handling in plugin manager
    * replaced `sw-grid` with `sw-data-grid` components in plugin manager
    * Remote address anonymization is now configurable in the settings at the Login / Registration module
    * We added error handling for delete requests. Since delete errors affect the whole entity it is not possible to store the error under a specific path. For this reason we provide a new getter `getErrorsForEntity` available through the State.
    * The object returned from Shopware.State.getters.getEntityError should always be treated read only.
* Core
    * Moved the seo module from the storefront into the core.
    * Switched the execution condition of `\Shopware\Core\Framework\Migration\MigrationStep::addBackwardTrigger()` and `\Shopware\Core\Framework\Migration\MigrationStep::addForwardTrigger()` to match the execution conditions in the methods documentation.
    * When a sub entity is written or deleted, a written event is dispatched for the configured root entity. 
        - Example for mapping entities: Writing a `product_category` entity now also dispatches a `product.written` and `category.written` event
        - Example for simple sub entities: Writing a `product_price` entity now also dispatches a `product_category` event
        - Example for nested sub entities: Writing a `order_delivery_position` entity now also dispatches a `order_delivery.written` and a `order.written` event
    * Required authentication for requests to `/api/v{version}/_info/business-events.json` and `/api/v{version}/_info/entity-schema.json` routes
    * Added `shopware.api.api_browser.auth_required` config value to configure if the access to the open api routes must be authenticated
    * Added a `seoUrls` `OneToManyAssociationField` to `product` and `category`.
    * Added a `SalesChannelSeoUrlDefinition` to filter by context language, sales channel and canonical.
    * Fixed a bug that `SalesChannelDefinition`s are not used for associations.
    * Added `metaTitle`, `metaDescription` and `keywords` columns to category entity
    * Added `metaDescription` to product entity
    * Added `campaignCode` and `affiliateCode` columns to customer and order entity
    * Added `TaxRuleEntity` to define country specific taxes
    * Added `TaxRuleTypeEntity` to define rule types for taxes
    * Added `rules` Association to tax entity
    * Added `buildTaxRules` to the `SalesChannelContext` to get the tax rules for the given `taxId` depending on the customer billing address
    * Removed `getTaxRuleCollection` from Product entity
    * Added `taxRuleTypeTranslations` association to Language entity
    * Added `taxRules` association to country entity
    * Changed the calling of `\Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition::getDefaults` which is now only called by newly created entities. The check `$existence->exists()` inside this method is not necessary anymore
    * Added new method `\Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition::getChildDefaults`. Use it to define defaults for newly created child entities
    * Extended the `\Shopware\Core\Checkout\Customer\SalesChannel\AccountRegistrationService::register` method which sets additional the double opt in data (only if set in the admin settings) and dispatches the respective event which send the mail with the confirm link
        - the confirm link includes the route to the `\Shopware\Storefront\Controller\RegisterController::confirmRegistration` method and two parameters (the sha1 hashed email address of the customer and a random generated hash)
    * Added new method `\Shopware\Core\Checkout\Customer\SalesChannel\AccountRegistrationService::finishDoubleOptInRegistration` which validates the double opt in confirmation and activates the customer when the data is valid
    * Added new event `\Shopware\Core\Checkout\Customer\Event\DoubleOptInGuestOrderEvent` for sending the confirm mail for double opt in guests
    * Added new event `\Shopware\Core\Checkout\Customer\Event\CustomerDoubleOptInRegistrationEvent` for sending the confirm mail for double opt in registrations
    * Extended the `\Shopware\Core\System\Resources\config\loginRegistration.xml` with input fields for double opt in registrations and guests
    * We adjusted the entry points for administration and storefront resources so that there are no naming conflicts anymore. It is no longer possible to adjust the paths to the corresponding sources. The new structure looks as follows:
        ```
        MyPlugin
         └──Resources
            ├── theme.json
            ├── app
            │   ├── administration
            │   │   └── src
            │   │       ├── main.js
            │   │       └── scss
            │   │           └── base.scss
            │   └── storefront
            │       ├── dist
            │       └── src
            │           ├── main.js
            │           └── scss
            │               └── base.scss
            ├── config
            │   ├── routes.xml
            │   └── services.xml
            ├── public
            │   ├── administration
            │   └── storefront
            └── views
                ├── administration
                ├── documents
                └── storefront
        ```
    * We unified the twig template directory structure of the core, administration and storefront bundle. Storefront template are now stored in a sub directory named `storefront`. This has an effect on the previous includes and extends:
        Before: 
        `{% sw_extends '@Storefront/base.html.twig' %}`
        After:
        `{% sw_extends '@Storefront/storefront/base.html.twig' %}`
    * We removed the corresponding public functions in the `Bundle.php`:
        * `getClassName`
        * `getViewPaths`
        * `getAdministrationEntryPath`
        * `getStorefrontEntryPath`
        * `getConfigPath`
        * `getStorefrontScriptPath`
        * `getStorefrontStylePath`
        * `getAdministrationStyles`
        * `getAdministrationScripts`
        * `getRoutesPath`
        * `getServicesFilePath`
    * We changed the accessibility of different internal `Bundle.php` functions
        * `registerContainerFile` from `protected` to `private`
        * `registerEvents` from `protected` to `private`
        * `registerFilesystem` from `protected` to `private`
        * `getContainerPrefix` from `protected` to `final public`
    * We changed implementation details for the state machine and the mail service
        * Added return type of method `getAvailableTransitions` in `\Core\System\StateMachine\StateMachineRegistry`. This method now has to return an array
        * changed return type of method `transition` in `\Core\System\StateMachine\StateMachineRegistry`. This method now returns `StateMachineStateCollection`
        * Added `StateMachineStateChangeEvent` to handle specific StateMachine Changes
        * Changed the technical_name for all stateMachine default mailTemplates by stripping the `state_enter` from the beginning.
        * Added optional Parameter `binAttachments` to method `createMessage` in `\Core\Content\MailTemplate\Service\MessageFactory` to provide binary attachments for mails.
        * Added `\Core\Checkout\Order\Api\OrderActionController` to provide endpoints for combine order state changes with sending of mails.  
    * Marked the `\Shopware\Core\Framework\Context::createDefaultContext()` as internal
    * Added relation between `order_line_item` and `product`.
    * Added validation for `order_line_item` of type `product`. If a line item of type `product` is written and one of the following properties is specified: `productId`, `referencedId`, `payload.productNumber`, the other two properties must also be specified.
    * Changed the order while loading plugins from the database. They are now sorted ascending by the installation date.
    * Moved `Shopware\Core\Content\Newsletter\SalesChannelNewsletterController` to `Shopware\Core\Content\Newsletter\SalesChannel\SalesChannelNewsletterController`
    * The MigrationController and MediaFolderController now return StatusCode 204 without content on successful requests
    * Renamed `\Shopware\Core\Framework\Api\Converter\ConverterService` to `\Shopware\Core\Framework\Api\Converter\ApiVersionConverter`
    * Added `\Shopware\Core\Framework\Demodata\PersonalData\CleanPersonalDataCommand` to removing personal data by the cli command: "bin/console database:clean-personal-data"
        * use the command with the argument "guests" to remove guests without orders
        * use the command with the argument "carts" to remove canceled carts
        * use the command with the option "--all" to remove both of them.
        * with the option "--days" it is possible to remove the data which is same old and older than the given number of days
    * Updated Symfony dependencies to version `4.4.0`.    
    * We removed the `\Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandInterface`, use `\Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand` instead
    * The sitemap generator now uses the `cache.system` pool instead of `serializer.mapping.cache.symfony`
    * Added sales channel type `product_comparison` for generating file exports of dynamic product groups 
    * We moved the namespace `Shopware\Core\Framework\Acl` to `Shopware\Core\Framework\Api\Acl`
    * We moved the namespace `Shopware\Core\Framework\CustomField` to `Shopware\Core\System\CustomField`
    * We moved the namespace `Shopware\Core\Framework\Language` to `Shopware\Core\System\Language`
    * We moved the namespace `Shopware\Core\Framework\Snippet` to `Shopware\Core\System\Snippet`
    * We moved the namespace `Shopware\Core\Framework\Doctrine` to `Shopware\Core\Framework\DataAbstractionLayer\Doctrine`
    * We moved the namespace `Shopware\Core\Framework\Pricing` to `Shopware\Core\Framework\DataAbstractionLayer\Pricing`
    * We moved the namespace `Shopware\Core\Framework\Version` to `Shopware\Core\Framework\DataAbstractionLayer\Version`
    * We moved the namespace `Shopware\Core\Framework\Faker` to `Shopware\Core\Framework\Demodata\Faker`
    * We moved the namespace `Shopware\Core\Framework\PersonalData` to `Shopware\Core\Framework\Demodata\PersonalData`
    * We moved the namespace `Shopware\Core\Framework\Logging` to `Shopware\Core\Framework\Log`
    * We moved the namespace `Shopware\Core\Framework\ScheduledTask` to `Shopware\Core\Framework\MessageQueue\ScheduledTask`
    * We moved the namespace `Shopware\Core\Framework\Twig` to `Shopware\Core\Framework\Adapter\Twig`
    * We moved the namespace `Shopware\Core\Framework\Asset` to `Shopware\Core\Framework\Adapter\Asset`
    * We moved the namespace `Shopware\Core\Framework\Console` to `Shopware\Core\Framework\Adapter\Console`
    * We moved the namespace `Shopware\Core\Framework\Cache` to `Shopware\Core\Framework\Adapter\Cache`
    * We moved the namespace `Shopware\Core\Framework\Filesystem` to `Shopware\Core\Framework\Adapter\Filesystem`
    * We moved the namespace `Shopware\Core\Framework\Translation` to `Shopware\Core\Framework\Adapter\Translation`
    * We moved the namespace `Shopware\Core\Framework\Seo` to `Shopware\Core\Content\Seo`
    * We moved the namespace `Shopware\Core\Framework\Context` to `Shopware\Core\Framework\Api\Context`
    * We moved the namespace `Shopware\Core\Content\DeliveryTime` to `Shopware\Core\System\DeliveryTime`
    * We moved the Shopware\Core\System\User\Service\UserProvisioner to Shopware\Core\System\User\Service\UserProvisioner
    * Added unique constraint for `iso_code` column in `currency` table
    * We moved the `Shopware\Storefront\Framework\Seo\SeoTemplateReplacementVariable` to `Shopware\Core\Content\Seo\SeoTemplateReplacementVariable`
    * We moved the `Shopware\Core\Content\ProductExport\SalesChannel\ProductExportController` to `Shopware\Storefront\Controller\ProductExportController`
    * Added `\Shopware\Core\Checkout\Customer\Subscriber\CustomerRemoteAddressSubscriber` to store remote addresses and updating the remote address data in the customer table
    * Added `\Shopware\Core\Framework\DataAbstractionLayer\Field\RemoteAddressField` to store remote address data
    * Added `\Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\RemoteAddressFieldSerializer` to check for the ip anonymous settings and anonymize the addresses if set
    * Added new `core_mailer` service which should be used to send mails. 
    * We added the `source` parameter for all entity api requests. This parameter allows to filter the returned fields.
    * The `\Shopware\Core\Framework\Api\Response\ResponseFactoryInterface::createDetailResponse` requires now a provided criteria
    * The `\Shopware\Core\Framework\Api\Response\ResponseFactoryInterface::createListingResponse` requires now a provided criteria
    * Removed `\Shopware\Core\Framework\Update\Event\UpdateFinishedEvent`
    * Added new events:
        * `\Shopware\Core\Framework\Update\Event\UpdatePrePrepareEvent` runs before the update with plugins enabled
        * `\Shopware\Core\Framework\Update\Event\UpdatePostPrepareEvent` runs before the update with plugins disabled
        * `\Shopware\Core\Framework\Update\Event\UpdatePreFinishEvent` runs after the update with plugins disabled
        * `\Shopware\Core\Framework\Update\Event\UpdatePostFinishEvent` runs after the update with plugins enabled
* Storefront
    * Changed `\Shopware\Storefront\Framework\Cache\CacheWarmer\CacheRouteWarmer` signatures
    * Moved most of the seo module into the core. Only storefront(route) specific logic/extensions remain
    * Added twig function `sw_csrf` for creating CSRF tokens
    * Added `Storefront/Resources/config/packages/storefront.yaml` configuration
    * Added `csrf` section to `storefront.yaml` configuration
    * Added `\Shopware\Storefront\Controller\CsrfController` for creating CSRF tokens(only if `csrf` `mode` is set to `ajax` in `storefront.yaml` configuration)
    * Added JS plugin for handling csrf token generation in native forms(only if `csrf` `mode` is set to `ajax`)
    * Added `MetaInformation` struct to handle meta information in `pageLoader`
    * Renamed the `breadcrumb` variable used in category seo url templates. It can now be access using `category.seoBreadcrumb` to align it with all other variables.
    * Added an automatic hot reload watcher with automatic detection. Use `./psh.phar storefront:hot-proxy` 
    * Extended the `\Shopware\Storefront\Controller\RegisterController::register` method with the double opt in logic (only if set in the admin settings)
    * Added new method `\Shopware\Storefront\Controller\RegisterController::confirmRegistration` to confirm double opt in registrations or email addresses
    * Added twig filter `sw_sanitize` to filter unwanted tags and attributes (prevent XSS)
    * The filter plugin moves to the offcanvas when the user is in mobile view
    * Updated Symfony Dependencies to version `4.4.0`.    
    * Added the `referencePrice` of a product to the `offcanvas cart` and the `ajax-search`
    * The default cache time for the theme config now uses the lifetime of the cache pool instead of 1 hour
    * The `theme.json` can now also define the order of the templates. For this you can use the `views` parameter:
        ```json
        {
            "views": [
                 "@Storefront",
                 "@Plugins",
                 "@SwagCustomizedProduct",
                 "@MyTheme",
                 "@SwagPayPal"
            ]
        }
      ```
    * Added the `async` Attribute to the main `<script>` tag.
    * The `src/Storefront/Resources/app/storefront/src/main.js` now uses the `readystatechange` event to initialize all JavaScript plugins.
    * Introduced new SCSS variable `$font-weight-semibold` with the value of `600`.
    * Added `fallbackImageTitle` variable to `src/Storefront/Resources/views/storefront/element/cms-element-image-gallery.html.twig` to add fallback values to the images `alt` and `title` attribute if the media object itself has no `alt` or `title` defined.
    * Added `cookie-configuration` plugin for displaying a cookie configuration menu 
    * Added global event `CookieConfiguration_Update` for updating the cookie preference
    * Moved `src/Storefront/Resources/app/storefront/src/plugin/cookie-permission/cookie-permission.plugin.js` to `src/Storefront/Resources/app/storefront/src/plugin/cookie/cookie-permission.plugin.js`
    * Moved `src/Storefront/Resources/views/storefront/layout/cookie-permission.html.twig` to `src/Storefront/Resources/views/storefront/layout/cookie/cookie-permission.html.twig`
* Elasticsearch
    * The env variables `SHOPWARE_SES_*` were renamed to `SHOPWARE_ES_*`.
        * You can set them with a parameter.yml too.
    * The extensions are now saved at the top level of the entities.
    * Updated `ongr/elasticsearch-dsl` to version `7.0.0`
    * Updated Symfony Dependencies to version `4.4.0`.    

**Removals**

* Administration
* Core
    * When a sub entity is written or deleted, a written event is dispatched for the configured root entity. 
        - Example for mapping entities: Writing a `product_category` entity now also dispatches a `product.written` and `category.written` event
        - Example for simple sub entities: Writing a `product_price` entity now also dispatches a `product_category` event
        - Example for nested sub entities: Writing a `order_delivery_position` entity now also dispatches a `order_delivery.written` and a `order.written` event
    * Removed seoUrls extensions in `product` and `category`. Use `product/category.seoUrls` instead 
    * Removed `shopware.api.api_browser.public` config value
    * Removed `Bundle::getAdministrationEntryPath`
    * Removed `Bundle::getStorefrontEntryPath`
    * Removed `Bundle::getConfigPath`
    * Removed `Bundle::getStorefrontScriptPath`
    * Removed `Bundle::getViewPaths`
    * Removed `Bundle::getRoutesPath`
    * Removed `Bundle::getServicesFilePath`
    * When a sub entity is written or deleted, a written event is dispatched for the configured root entity. 
        - Example for mapping entities: Writing a `product_category` entity now also dispatches a `product.written` and `category.written` event
        - Example for simple sub entities: Writing a `product_price` entity now also dispatches a `product_category` event
        - Example for nested sub entities: Writing a `order_delivery_position` entity now also dispatches a `order_delivery.written` and a `order.written` event
    * Dropped `additionalText` column of product entity, use `metaDescription` instead
    * Removed `EntityExistence $existence` parameter from `\Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition::getDefaults` as it is not necessary anymore
    * Removed configuration `shopware.entity_cache` in favor of the symfony cache pool `cache.object` configuration.
    * Removed the `voku/stop-words` package
    * Removed the `SearchFilterInterface` and `StopWordFilter`, which were not used anywhere 
* Storefront
    * Removed `\Shopware\Storefront\Framework\Cache\CacheWarmer\Navigation\NavigationRouteMessage`
    * Removed `\Shopware\Storefront\Framework\Cache\CacheWarmer\Product\ProductRouteMessage`
    * Removed `\Shopware\Storefront\Framework\Cache\CacheWarmer\CacheWarmerSender`
    * Removed `\Shopware\Storefront\Framework\Cache\CacheWarmer\IteratorMessage`
    * Removed `\Shopware\Storefront\Framework\Cache\CacheWarmer\IteratorMessageHandler`
    * Removed unused font variants:
        * Removed vendor css file for the "Inter" font face: `src/Storefront/Resources/app/storefront/vendor/Inter-3.5/inter.css`. The font file imports can now be found in `src/Storefront/Resources/app/storefront/src/scss/skin/shopware/vendor/_inter-fontface.scss`.
        * Removed `src/Storefront/Resources/app/storefront/dist/assets/font/Inter-Black.woff`
        * Removed `src/Storefront/Resources/app/storefront/dist/assets/font/Inter-Black.woff2`
        * Removed `src/Storefront/Resources/app/storefront/dist/assets/font/Inter-BlackItalic.woff`
        * Removed `src/Storefront/Resources/app/storefront/dist/assets/font/Inter-BlackItalic.woff2`
        * Removed `src/Storefront/Resources/app/storefront/dist/assets/font/Inter-ExtraBold.woff`
        * Removed `src/Storefront/Resources/app/storefront/dist/assets/font/Inter-ExtraBold.woff`
        * Removed `src/Storefront/Resources/app/storefront/dist/assets/font/Inter-ExtraBoldItalic.woff`
        * Removed `src/Storefront/Resources/app/storefront/dist/assets/font/Inter-ExtraBoldItalic.woff2`
        * Removed `src/Storefront/Resources/app/storefront/dist/assets/font/Inter-ExtraLight-BETA.woff`
        * Removed `src/Storefront/Resources/app/storefront/dist/assets/font/Inter-ExtraLight-BETA.woff2`
        * Removed `src/Storefront/Resources/app/storefront/dist/assets/font/Inter-ExtraLightItalic-BETA.woff`
        * Removed `src/Storefront/Resources/app/storefront/dist/assets/font/Inter-ExtraLightItalic-BETA.woff2`
        * Removed `src/Storefront/Resources/app/storefront/dist/assets/font/Inter-Light-BETA.woff`
        * Removed `src/Storefront/Resources/app/storefront/dist/assets/font/Inter-Light-BETA.woff2`
        * Removed `src/Storefront/Resources/app/storefront/dist/assets/font/Inter-LightItalic-BETA.woff`
        * Removed `src/Storefront/Resources/app/storefront/dist/assets/font/Inter-LightItalic-BETA.woff2`
        * Removed `src/Storefront/Resources/app/storefront/dist/assets/font/Inter-Medium.woff`
        * Removed `src/Storefront/Resources/app/storefront/dist/assets/font/Inter-Medium.woff2`
        * Removed `src/Storefront/Resources/app/storefront/dist/assets/font/Inter-MediumItalic.woff`
        * Removed `src/Storefront/Resources/app/storefront/dist/assets/font/Inter-Medium.woff2`
        * Removed `src/Storefront/Resources/app/storefront/dist/assets/font/Inter-MediumItalic.woff`
        * Removed `src/Storefront/Resources/app/storefront/dist/assets/font/Inter-MediumItalic.woff2`
        * Removed `src/Storefront/Resources/app/storefront/dist/assets/font/Inter-Thin-BETA.woff`
        * Removed `src/Storefront/Resources/app/storefront/dist/assets/font/Inter-Thin-BETA.woff2`
        * Removed `src/Storefront/Resources/app/storefront/dist/assets/font/Inter-ThinItalic-BETA.woff`
        * Removed `src/Storefront/Resources/app/storefront/dist/assets/font/Inter-ThinItalic-BETA.woff2`
        * Removed `src/Storefront/Resources/app/storefront/dist/assets/font/Inter-italic.var.woff2`
        * Removed `src/Storefront/Resources/app/storefront/dist/assets/font/Inter-upright.var.woff2`
        * Removed `src/Storefront/Resources/app/storefront/dist/assets/font/Inter.var.woff2`
