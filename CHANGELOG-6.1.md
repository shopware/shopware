CHANGELOG for 6.1.x
===================

This changelog references the relevant changes (bug and security fixes) done
in 6.1 minor versions.

To get the diff for a specific change, go to https://github.com/shopware/platform/commit/XXX where XXX is the change hash
To get the diff between two versions, go to https://github.com/shopware/platform/compare/v6.1.0-rc1...v6.1.0-rc2

### 6.1.1

**Addition / Changes**

* Core
    * Added a check for the author role in plugin composer.json files. If one or more authors have the role `Manufacturer`, only these will be written to the database.

* Recovery
    * The database connection now supports sockets

### 6.1.0

**Addition / Changes**

* Administration
	* Added `getComponentHelper` to global `Shopware` object	
    * Added async loading of plugins	
    * Added seperation of login and application boot
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
    * Added default shop page layouts for contact and newsletter form	
    * Added event `delte-item-failed` to both `sw-entity-listing` and `sw-one-to-many-listing` which is emitted if the delete request for an entity fails. The event data is an object containing a `id` and `errorResponse` property.	
    * Added block `block sw_customer_address_form_state_field` to `sw-customer-address-form` component that that contains an `sw-entity-single-select` to specify a state for addresses	
    * Added `countryState` in `sw-address`    
    * Updated `nuxt` to `2.10.2` in the `nuxt-component-library` package
    * Updated `dompurify` to `2.0.7` in the `administration` package
    * Updated `cypress-select-tests` to `1.4.1` in the `administration/e2e` package
    * Updated `copy-webpack-plugin` to `5.1.1` in the `common/webpack-plugin` package
    * Added new Block `sw_media_quickinfo_metadata_specific_meta_data` to sw-media-quickinfo that is only rendered if metadata could be fetched for uploaded media. This also gives you easier access to extend the metadata section for specific file types.
    * Changes to `sw-plugin-last-updates-grid`:
      * Reorganized content of `sw-plugin-last-updates-grid`. It now displays only the empty state or grid but not both.
      * We moved the condition when the empty state is shown to the slot access itself rather than to the `sw-empty-state` component.
      * Added new block `sw_plugin_last_udates_card_grid_content` in `sw-plugin-last-updates-grid` to override the grid content rather than the slot access.
    * Removed computed property `lineItemActionsEnabled` from `sw-order-line-items-grid` since it was never used and always evaluate to false
    * Replaced kebab-case plugin file exporting with camel case to match php requirements
    * Added pagination to plugin manager
    * Show domain selection in first run wizard only when domains exists
    * Hide user set groups option in promotions behind an experimental flag
    * Added new block `sw_promotion_cart_condition_form_allow_experimental`
    * When updating domains in a sales channel you can only select one of the available languages for the sales channel
    * Fix module meta information in extended module routes with the routeMiddleware
    * Replace old currency isDefault with isSystemDefault
    * Replace block `sw_property_detail_content_option_list` in `sw-property-create` with empty content
    * Add `setLocaleFromUser` method to vue adapter
    * Add service `localeHelper` for changing the locale
    * Use service `localeHelper` on every place where you can change the locale
    * Refactor `session.store.js`: action `setAdminLocale` return now a Promise
    * Refactor `snippet.api.service.js`: add paramter code which expects the locale code
    * Refactor `sw-profile-index`
        * Remove data `userProfile`
        * Replace `setUserData` to `getUserData`
        * `saveFinish` is now an asynchronous method
    * Fix `sw-multi-ip-select` value property if it is undefined
    * Removed property type check for value property of `sw-multi-select` and `sw-multi-ip-select` because the value is already checked in a custom validator function
     
    * To make the headers of a column in the `sw-data-grid` translatable you have to specify only the path to the snippet. `this.$tc('path.to.snippet')` will still work. 

    * Fixed the inheritance reset for the price field in the variant listing.
    * Fixed product name column in product review listing
* Core    
	* We did some refactoring on how we use `WriteConstraintsViolationExceptions`.	
        It's path `property` should now point to the object that is inspected by an validator while the `propertyPath` property in `WriteConstraint` objects should only point to the invalid property. 	
        For more information read the updated "write command validation" article in the docs.
    * Added the `extractInheritableAttributes()` function to the `\Shopware\Core\Framework\Routing\RequestTransformerInterface`
	* Added ErrorPage, ErrorpageLoader and ErrorPageLoaderEvent which is used in the `ErrorController` to load the CMS error layout if a 404 layout is assigned.	
    * Added an option to disable eslint for storefront:build	
    * Removed abandoned TwigExtensions in favor of  Twig Core Extra extensions
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
            │   ├── administration	
            │   │   └── src	
            │   │       ├── main.js	
            │   │       └── scss	
            │   │           └── base.scss	
            │   └── storefront	
            │       ├── dist	
            │       └── src	
            │           ├── main.js	
            │           └── scss	
            │               └── base.scss	
            ├── config	
            │   ├── routes.xml	
            │   └── services.xml	
            ├── public	
            │   ├── administration	
            │   └── storefront	
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
    * The `\Shopware\Core\Framework\Api\Response\ResponseFactoryInterface::createListingResponse` requires now a provided criteria	
    * Changed the error code of `ConstrainViolationExeption` from `FRAMEWORK::CONSTRAINT_VIOLATION` to  `FRAMEWORK__CONSTRAINT_VIOLATION`	
    * Added the `protected` function `ShopwareHttpError::getCommonErrorData` which returns you the array that was usually yielded by `ShopwareHttpError::getErrors`. With the new function it is easier to extend error information before yielding it back.	
       ```php	
       // before	
       class newException extends ShopwareHttpExeption {	
         public funtion getErrors() {	
           // usually the parent function yields one element only	
           foreach(parent::getErrors() as $parentError) {	
               $parentError['someNewField'] = 'some new Data';	
               yield $parentError;	
           }	
         } 	
       }	
 	
       // after	
       class newException extends ShopwareHttpExeption {	
         public funtion getErrors() {	
           $errorData = $this->getCommonErrorData();	
           $errorData['someNewField'] = 'some new Data';	
           yield $parentError;	
         } 	
       }	
       ```	
    * Fixed a bug that cms configuration could not be overridden if some default config is null.	
    * We added a check to `lineItem.payload.productNumber` before calling the twig truncate function	
    * Deprecated `\Shopware\Core\Framework\Validation\ValidationServiceInterface`, it will be removed in 6.3.0
    * Added the interface `\Shopware\Core\Framework\Validation\DataValidationFactoryInterface` that will replace the deprecated `\Shopware\Core\Framework\Validation\ValidationServiceInterface`
    * Removed the `\Shopware\Core\Framework\Validation\ValidationServiceInterface` from the `\Shopware\Core\Content\Seo\Validation\SeoUrlValidationService`, added the `\Shopware\Core\Content\Seo\Validation\SeoUrlDataValidationFactoryInterface` instead to allow service decoration
    * Renamed `\Shopware\Core\Content\Seo\Validation\SeoUrlValidationService` to `\Shopware\Core\Content\Seo\Validation\SeoUrlValidationFactory`, the old serviceId is now deprecated 
    * Renamed `\Shopware\Core\Checkout\Customer\Validation\AddressValidationService` to `\Shopware\Core\Checkout\Customer\Validation\AddressValidationFactory`, the old serviceId is now deprecated 
    * Renamed `\Shopware\Core\Checkout\Customer\Validation\CustomerValidationService` to `\Shopware\Core\Checkout\Customer\Validation\CustomerValidationFactory`, the old serviceId is now deprecated 
    * Renamed `\Shopware\Core\Checkout\Customer\Validation\CustomerProfileValidationService` to `\Shopware\Core\Checkout\Customer\Validation\CustomerProfileValidationFactory`, the old serviceId is now deprecated 
    * Renamed `\Shopware\Core\Checkout\Order\Validation\OrderValidationService` to `\Shopware\Core\Checkout\Order\Validation\OrderValidationFactory`, the old serviceId is now deprecated 
    * Renamed `\Shopware\Core\Content\ContactForm\Validation\ContactFormValidationService` to `\Shopware\Core\Content\ContactForm\Validation\ContactFormValidationFactory`, the old serviceId is now deprecated 
    * Fixed a bug in storefront search that occurred when keywords such as \0\0 were entered.
    * Added a position field on the `\Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition`, used for sorting the line items
    * Changed default `shopware.cdn.strategy` to the new `physical_pathname` strategy that behaves like the old `md5` strategy. For new installations the default is `id`
    * Deprecated `\Shopware\Core\Framework\Plugin::getExtraBundles`, use `getAdditionalBundles`
    * Added method `\Shopware\Core\Framework\Plugin::getAdditionalBundles` method with additional parameters compared to `getExtraBundles`, to allow loading bundles depending on other plugins/bundles and kernel parameters.
    * Change default `shopware.cdn.strategy` to the new `physical_pathname` strategy that behaves like the old `md5` strategy. For new installations the default is `id`
    * Fixed a bug where entities got removed by deleting default version. Deleting default version via `/api/v{version}/_action/version/{versionId}/{entity}/{entityId}` is now forbidden
    * The data format of the `lineItem.payload.options` has changed. Now there is a simple array per element with `option` and `group`. It contains the translated names of the entities.
    * We deprecated the `Shopware\Storefront\Page\Search\SearchPage::$searchResult` property, use `Shopware\Storefront\Page\Search\SearchPage::$listing` instead
    * We implemented the new `Shopware\Core\HttpKernel` class which simplifies the kernel initialisation.
    * Marked the `\Shopware\Core\Framework\Api\ApiDefinition\ApiDefinitionGeneratorInterface` and it's implementations as internal
    * Added the `version` parameter to the methods of the `\Shopware\Core\Framework\Api\ApiDefinition\ApiDefinitionGeneratorInterface`    
    * We deprecated the `\Shopware\Core\Content\Seo\SeoUrlGenerator::generateSeoUrls` function, use `\Shopware\Core\Content\Seo\SeoUrlGenerator::generate` instead
    * We deprecated the `\Shopware\Core\Content\Seo\SeoUrlGenerator::checkUpdateAffectsTemplate` function
    * `@Framework/documents/style_base_portrait.css.twig` and `@Framework/documents/style_base_landscape.css.twig` are now included by `sw_include`.
    * Added new `$depth` parameter to `\Shopware\Core\Content\Category\Service\NavigationLoader::load`
    * Added new field `navigation_category_depth` to `\Shopware\Core\System\SalesChannel\SalesChannelDefinition`
    * Added new `searchMedia` twig function to resolve media ids to media objects. 
        * `{% set media = searchMedia(ids, context) %}`
    * Added new field `navigation_category_depth` to `\Shopware\Core\System\SalesChannel\SalesChannelDefinition` 
    * Changed `MailService` to add `bcc` instead `to` for `deliveryAddress`
    * We added the `Inherited` flag to the `listingPrices` property of the `\Shopware\Core\Content\Product\ProductDefinition`
    * The `\Shopware\Storefront\Page\Product\Review\ProductReviewLoader::load` returns now the reviews of all variants.
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
    * Added XHtmlRequest route to `/country/country-state-data`	
    * Added `CountryStateController`	
    * Added JavaScript Plugin `CountryStateSelect` that handles selectable states for selected a country	
    * Added blocks to display select to handle the state in address forms	
    * Encapsulated select inputs for country and state in a single form row	
    * Added `StorefrontMediaUploader` to handle file uploads in the storefront and validate them using `StorefrontValidationRegistry` and `StorefrontMediaValidatorInterface`	
    * Removed return type from `\Shopware\Core\Checkout\Cart\LineItem\LineItem::getPayloadValue()` 	
    * Fixed external category links in footer and service navigation
    * Updated `copy-webpack-plugin` to `5.1.1` in the `storefront` package
    * Updated `terser-webpack-plugin` to `2.2.3` in the `storefront` package
    * We have refactored the file `Storefront/Resources/views/storefront/layout/navigation/offcanvas/navigation.html.twig`. It was split into smaller template files.
    * The js plugin manager now catches errors from the plugin initialization to avoid stopping the script if only one plugin fails.
    * We removed all dependencies to media metadata in storefront.
    * You can now disable the lint plugin by setting `ESLINT_DISABLE` environment variable to `'true'`.
      * Run `APP_URL="<your url>" PLATFORM_ROOT=/app/ ESLINT_DISABLE=true npm run hot` in Storefront js folder
    * The Lint plugin can only be disabled in hot reload mode.
    * We extended setup of the `storefront:hot-proxy`
      * The proxy now points to your app url's host instead of `localhost` which means that the url only differs in the port.
      * The port is now replaced in both regular page requests and XHtmlRequests.
      * The proxy's port is now configurable.
        * Using psh: just override the `STOREFRONT_PROXY_PORT` constant (this will also map the port for docker setup)
        * Using npm: run `APP_URL="<your url>" STOREFRONT_PROXY_PORT=<some port> PROJECT_ROOT=<path to your root folder>/ npm run hot-proxy` from the storefronts js directory.
      * The default port is still port 9998.
    * We implemented the new `Storefront/Resources/views/storefront/component/product/listing.html.twig` which can be included to display product listings
    * Changed the naming of the method `_submitForm` to `_redirectToVariant` inside `src/Storefront/Resources/app/storefront/src/plugin/variant-switch/variant-switch.plugin.js`.
    * Added the `!default` flag to all variable declarations in the following SCSS files to provide the ability to modify the default values inside a theme:
        * `src/Storefront/Resources/app/storefront/src/scss/abstract/variables/_bootstrap.scss`
        * `src/Storefront/Resources/app/storefront/src/scss/abstract/variables/_custom.scss`
        * `src/Storefront/Resources/app/storefront/src/scss/skin/shopware/abstract/variables/_bootstrap.scss`
        * `src/Storefront/Resources/app/storefront/src/scss/skin/shopware/abstract/variables/_custom.scss`
    * Fixed the cookie privacy hint to use the correct link `privacyPage` instead of `shippingPaymentInfoPage`
    * Added the parameter `useBackdrop` to the `page-loading-indicator.utils.js` `remove` and `create` methods. Defaults to `true`
* Elasticsearch	
    * The env variables `SHOPWARE_SES_*` were renamed to `SHOPWARE_ES_*`.
        * You can set them with a parameter.yml too.
    * The extensions are now saved at the top level of the entities.	
    * Updated `ongr/elasticsearch-dsl` to version `7.0.0`	
    * Updated Symfony Dependencies to version `4.4.0`.   
    
**Removals**

* Administration
    * Removed module export of `Shopware`
    * Removed plugin functionality in login	
    * Removed direct component registration in modules
    * Removed "add order" button in order module
    * Remove disabled cms page type for product pages
    
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
    * Removed return type hint of `Shopware\Core\Framework\Struct\Collection::reduce` 	

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
    * Removed `ContactPageController` and the `contact page`	
    * Removed `newsletter page` and its route `/newsletter`   
