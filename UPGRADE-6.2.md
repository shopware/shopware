UPGRADE FROM 6.2.x to 6.2.3
=======================

* The `user` entity is now write protected via api. To create, update or delete a `user` entity, you need the `user-verified` oauth-scope. The scope can be requested over the `/api/oauth/token` route.
    ```php
    $client->request('POST', '/api/oauth/token', [
        'grant_type' => 'password',
        'client_id' => 'administration',
        'username' => 'admin',
        'password' => 'shopware',
        'scope' => ['user-verified'],
    ]);
    ```

UPGRADE FROM 6.1.x to 6.2
=======================

Table of content
----------------

* [Core](#core)
* [Administration](#administration)
* [Storefront](#storefront)
* [Refactorings](#refactorings)
  + [DAL Indexer refactoring](#dal-indexer-refactoring)
    - [Consequences](#consequences)
    - [Function of an indexer](#function-of-an-indexer)
    - [The new base class](#the-new-base-class)
    - [Example indexer](#example-indexer)
    - [DAL fields with indexing](#dal-fields-with-indexing)
    - [Seo Urls](#seo-urls)
    - [MySQL Deadlocks](#mysql-deadlocks)


Core
----

* The usage of `entity` in the `shopware.entity.definition` tag is deprecated and will be removed with 6.4.
    * Therefore change:
        `<tag name="shopware.entity.definition" entity="product"/>`
      To:
        `<tag name="shopware.entity.definition"/>`
    * As a fallback, this function is used first 
* We deprecated the `LongTextWithHtmlField` in 6.2, use `LongTextField` with `AllowHtml` flag instead
* The Mailer is not overwritten anymore, instead the swiftmailer.transport is decorated.
    * Therefore the MailerFactory returns a Swift_Transport Instance instead of Swift_Mailer
    * The MailerFactory::create Method is deprecated now

    Before: 
    ```
    new LongTextWithHtmlField('content', 'content')
    ```
  
    After:  
    ```
    (new LongTextField('content', 'content'))->addFlags(new AllowHtml()
    ```
* CartBehavior::isRecalculation is deprecated and will be removed in version 6.3
* Please use context permissions instead:
    * Permissions can be configured in the SalesChannelContext.
    * `CartBehavior` is created based on the permissions from `SalesChannelContext`, you can check the permissions at this class.
    * Permissions exists:
         `ProductCartProcessor::ALLOW_PRODUCT_PRICE_OVERWRITES`
         `ProductCartProcessor::SKIP_PRODUCT_RECALCULATION`
         `DeliveryProcessor::SKIP_DELIVERY_RECALCULATION`
         `PromotionCollector::SKIP_PROMOTION`
    * Define permissions for AdminOrders at class `SalesChannelProxyController` within the array constant `ADMIN_ORDER_PERMISSIONS`.
    * Define permissions for the Recalculation at class `OrderConverter` within the array constant `ADMIN_EDIT_ORDER_PERMISSIONS`.
    * Extended permissions with subscribe event `SalesChannelContextPermissionsChangedEvent`, see detail at class `SalesChannelContextFactory`
* The usage of `$connection->executeQuery()` for write operations is deprecated, use 
`$connection->executeUpdate()` instead.
* For the possibility to add individual product to across selling, you need to use a new field for creating a cross selling
    * Please use type `productList` or `productStream` in order to create a corresponding cross selling
* The `\Shopware\Core\Framework\DataAbstractionLayer\EntityExtensionInterface` will be removed, extend from the abstract class `\Shopware\Core\Framework\DataAbstractionLayer\EntityExtension` instead.
* Deprecated `\Shopware\Core\Framework\Routing\RouteScopeInterface` use abstract class `\Shopware\Core\Framework\Routing\AbstractRouteScope` instead
* Deprecated `\Shopware\Core\Content\ContactForm\ContactFormService` use the new service `\Shopware\Core\Content\ContactForm\SalesChannel\ContactFormRoute` instead
* Deprecated `\Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingGateway` use `\Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRouteInterface` instead
* Deprecated `\Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingGatewayInterface` use `\Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRouteInterface` instead
* Deprecated `\Shopware\Core\Content\Product\SalesChannel\Search\ProductSearchGateway` use `\Shopware\Core\Content\Product\SalesChannel\Search\ProductSearchRouteInterface` instead
* Deprecated `\Shopware\Core\Content\Product\SalesChannel\Search\ProductSearchGatewayInterface` use `\Shopware\Core\Content\Product\SalesChannel\Search\ProductSearchRouteInterface` instead
* Deprecated `\Shopware\Core\Content\Product\SalesChannel\Suggest\ProductSuggestGatewayInterface` use `\Shopware\Core\Content\Product\SalesChannel\Search\ProductSearchRouteInterface` instead
* Deprecated `\Shopware\Core\Content\Product\SalesChannel\Suggest\ProductSuggestGateway` use `\Shopware\Core\Content\Product\SalesChannel\Search\ProductSearchRouteInterface` instead
* Deprecated `\Shopware\Core\Checkout\Customer\SalesChannel\AccountService` use one of the following new services
    * `\Shopware\Core\Checkout\Customer\SalesChannel\ChangeCustomerProfileRoute`
    * `\Shopware\Core\Checkout\Customer\SalesChannel\ChangeEmailRoute`
    * `\Shopware\Core\Checkout\Customer\SalesChannel\ChangePasswordRoute`
    * `\Shopware\Core\Checkout\Customer\SalesChannel\ChangePaymentMethodRoute`
    * `\Shopware\Core\Checkout\Customer\SalesChannel\LoginRoute`
    * `\Shopware\Core\Checkout\Customer\SalesChannel\LogoutRoute`
    * `\Shopware\Core\Checkout\Customer\SalesChannel\SendPasswordRecoveryMailRoute`
    * `\Shopware\Core\Checkout\Customer\SalesChannel\ResetPasswordRoute`
* Deprecated `\Shopware\Core\Content\Newsletter\NewsletterSubscriptionServiceInterface` and `\Shopware\Core\Content\Newsletter\NewsletterSubscriptionService` use one of the following new services
    * `\Shopware\Core\Content\Newsletter\SalesChannel\NewsletterSubscribeRoute`
    * `\Shopware\Core\Content\Newsletter\SalesChannel\NewsletterUnsubscribeRoute`
    * `\Shopware\Core\Content\Newsletter\SalesChannel\NewsletterConfirmRoute`
* Deprecated `\Shopware\Core\Checkout\Customer\SalesChannel\AccountRegistrationService` use one of the following new services
    * `\Shopware\Core\Checkout\Customer\SalesChannel\RegisterRouteInterface`
    * `\Shopware\Core\Checkout\Customer\SalesChannel\RegisterConfirmRouteInterface`
* Deprecated `\Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackagerInterface` use `\Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackager` instead
* Added optional second parameter `$context` to `\Shopware\Core\Framework\Plugin\PluginManagementService::uploadPlugin` and `\Shopware\Core\Framework\Plugin\PluginManagementService::deletePlugin`. It will be required in 6.3.0
* Deprecated `\Shopware\Core\Framework\Plugin\PluginManagementService::extractPluginZip` which will be private in 6.3.0
* Added optional third parameter `$definition` to `Shopware\Elasticsearch\Framework\ElasticsearchHelper::addTerm`. It will be required in 6.3.0.
* Added a new translatable `label` property to `\Shopware\Core\Content\ImportExport\ImportExportProfileDefinition`
    * This property is required
    * The name may be omitted now
* Added new message `\Shopware\Core\Framework\Adapter\Cache\Message\CleanupOldCacheFolders` to cleanup old cache folders in `var/cache`

Administration
--------------

* `sw-settings-custom-field-set`
    - Removed method which overrides the mixin method `getList`, use the computed `listingCriteria` instead
    - Add computed property `listingCriteria`
* `sw-settings-document-list`
    - Removed method which overrides the mixin method `getList`, use the computed `listingCriteria` instead
    - Add computed property `listingCriteria`
* Refactor  `sw-settings-snippet-list`
    - Removed `StateDeprecated`
    - Remove computed property `snippetSetStore`, use `snippetSetRepository' instead
    - Add computed property `snippetSetRepository`
    - Add computed property `snippetSetCriteria`
* Refactor `sw-settings-snippet-set-list`
    - Remove `StateDeprecated`
    - Remove computed property `snippetSetStore`, use `snippetSetRepository' instead
    - Add computed property `snippetSetCriteria`
    - The method `onConfirmClone` is now an asynchronous method
* Refactor mixin `sw-settings-list.mixin`
    - Remove `StateDeprecated`
    - Remove computed property `store`, use `entityRepository` instead
    - Add computed property `entityRepository`
    - Add computed property `listingCriteria`
* The component sw-plugin-box was refactored to use the "repositoryFactory" instead of "StateDeprecated" to fetch and save data
        - removed "StateDeprecated"
        - removed computed "pluginStore" use "pluginRepository" instead
* The component sw-settings-payment-detail was refactored to use the "repositoryFactory" instead of "StateDeprecated" to fetch and save data
    - removed "StateDeprecated"
    - removed computed "paymentMethodStore" use "paymentMethodRepository" instead
    - removed computed "ruleStore" use "ruleRepository" instead
    - removed computed "mediaStore" use "mediaRepository" instead
* Refactor the module `sw-settings-number-range-detail`
    * Remove LocalStore
    * Remove StateDeprecated
    * Remove data typeCriteria
    * Remove data numberRangeSalesChannelsStore
    * Remove data numberRangeSalesChannels
    * Remove data numberRangeSalesChannelsAssoc
    * Remove data salesChannelsTypeCriteria
    * Remove computed numberRangeStore use numberRangeRepository instead
    * Remove computed firstSalesChannel
    * Remove computed salesChannelAssociationStore
    * Remove computed numberRangeStateStore use numberRangeStateRepository instead
    * Remove computed salesChannelStore use salesChannelRepository instead
    * Remove computed numberRangeTypeStore use numberRangeTypeRepository instead
    * Remove method onChange
    * Remove method showOption
    * Remove method getPossibleSalesChannels
    * Remove method setSalesChannelCriteria
    * Remove method enrichAssocStores
    * Remove method onChangeSalesChannel
    * Remove method configHasSaleschannel
    * Remove method selectHasSaleschannel
    * Remove method undeleteSaleschannel

* Refactored `sw-newsletter-recipient-list`
    * Removed LocalStore
    * Removed StateDeprecated
    * Removed Computed salesChannelStore, use salesChannelRepository instead
    * Removed Computed tagStore, use tagRepository instead
    * Removed Computed tagAssociationStore

* Refactored mapErrorService
    * Deprecated `mapApiErrors`, use `mapPropertyErrors`
    * Added `mapCollectionPropertyErrors` to mapErrorService for Entity Collections
* Deprecated `sw-multi-ip-select` with version 6.4, use the `sw-multi-tag-ip-select`-component instead
* Replaced Store based datahandling with repository based datahandling in media specific components and modules, including the following changes
    * sw-media-field is deprecated and replaced by sw-media-field-v2
        * Added injection of `repositoryFactory`
        * Replaced computed property `mediaStore` with `mediaRepository`
        * Method `fetchItem` is now async
        * Method `fetchSuggestions` is now async
    * sw-media-list-selection is deprecated and replaced by sw-media-list-selection-v2
        * Added injection of `repositoryFactory`
        * Added injection of `mediaService`
        * Replaced computed property `mediaStore` with `mediaRepository`
        * Method `onUploadsAdded` is now async
        * Method `successfulUpload` is now async
    * sw-media-preview is deprecated and replaced by sw-media-preview-v2
        * Added injection of `repositoryFactory`
        * Replaced computed property `mediaStore` with `mediaRepository`
        * Method `fetchSourceIfNecessary` is now async
        * Method `getDataUrlFromFile` is now async
    * sw-media-upload is deprecated and replaced sw-media-upload-v2
        * Added injection of `repositoryFactory`
        * Added injection of `mediaService`
        * Replaced computed property `defaultFolderStore` with `defaultFolderRepository`
        * Watcher of prop `defaultFolder` is now async
        * Method `createdComponent` is now async
        * Method `onUrlUpload` is now async
        * Method `handleUpload` is now async
        * Method `getDefaultFolderId` is now async
        * Replaced method `handleUploadStoreEvent` with `handleMediaServiceUploadEvent`
    * sw-duplicated-media is deprecated and replaced sw-duplicated-media-v2 
        * Added injection of `repositoryFactory`
        * Replaced computed property `mediaStore` with `mediaRepository`
        * Replaced method `handleUploadStoreEvent` with `handleMediaServiceUploadEvent`
        * Method `updatePreviewData` is now async
        * Method `renameFile` is now async
        * Method `skipFile` is now async
        * Method `replaceFile` is now async
    * sw-media-modal is deprecated and replaced by sw-media-modal-v2
        * Added injection of `repositoryFactory`
        * Added injection of `mediaService`
        * Replaced computed property `mediaStore` with `mediaRepository`
        * Replaced computed property `mediaFolderStore` with `mediaFolderRepository`
        * Method `fetchCurrentFolder` is now async
        * Method `onUploadsAdded` is now async
        * Method `onUploadsFinished` is now async
    * sw-upload-store-listener  is deprecated and replaced by sw-upload-listener
        * Added injection of `repositoryFactory`
        * Added injection of `mediaService`
        * Added computed property `mediaRepository`
        * Method `handleError` is now async
    * sw-media-compact-upload is deprecated and replaced by sw-media-compact-upload-v2
* Deprecated method `getFields`, use `getStructuredFields` instead
* Removed module `sw-settings-newsletter-config` and component `sw-settings-newsletter-config`

* Refactored settings items list
    * Deprecated `sw_settings_content_card_slot_plugins` twig block with version 6.4
    * If your Plugin extends `src/module/sw-settings/page/sw-settings-index/sw-settings-index.html.twig`
      to appear in the "plugins" tab on settings page, 
      remove the extension from your plugin and add an `settingsItem` Array to your Module instead:
      
        ```
        Module.register('my-awesome-plugin') {
            // ...
            settingsItem: [
                {
                    name:   'my-awesome-plugin',             // unique name
                    to:     'my.awesome.plugin.index',       // dot notated route to your plugin's settings index page  
                    label:  'my.awesome.plugin.title',       // translation snippet key
                    group:  'plugins',                       // register plugin under "plugins" tab in settings page
                    icon:   'use a shopware icon here',
                    // OR
                    component: 'component'                   // use a component here, if you want to render the icon in any other way            
                }
                // ... more settings items
            ]
        }
        ```
* Implemented the possibility to add individual product to cross selling
    * Added `sw-product-cross-selling-assignment` component

* CustomFields are now sorted naturally when custom position is used with customFieldPosition (for example 1,9,10 instead of 1,10,9)
* The id of checkbox and switch fields are now unique. Therefore you have to update your selectors if you want to get the fields by id.
This was an important change, because every checkbox and switch field has the same id. This causes problems when you click
on the corresponding label.

* Added block `sw_sales_channel_detail_analytics_fields_anonymize_ip` to `sw-sales-channel-detail-analytics.html.twig`


Storefront
----------

* We removed the SCSS skin import `@import 'skin/shopware/base'` inside `/Users/tberge/www/sw6/platform/src/Storefront/Resources/app/storefront/src/scss/base.scss`.
    * If you don't use the `@Storefront` bundle in your `theme.json` and you are importing the shopware core `base.scss` manually you have to import the shopware skin too in order to get the same result:

        Before
        ```
        @import "../../../../vendor/shopware/platform/src/Storefront/Resources/app/storefront/src/scss/base.scss";
        ```

        After
        ```
        @import "../../../../vendor/shopware/platform/src/Storefront/Resources/app/storefront/src/scss/base.scss";
        @import "../../../../vendor/shopware/platform/src/Storefront/Resources/app/storefront/src/scss/skin/shopware/base";
* We changed the storefront ESLint rule `comma-dangle` to `never`, so that trailing commas won't be forcefully added anymore
* The theme manager supports now tabs. Usage: 
```json
"config": {
    "tabs": {
        "colors": {
            "label": {
                "en-GB": "Colours",
                "de-DE": "Farben"
            }
        },
    },
    "blocks": {
    ...
    },
    ...,
    "fields": {
        "sw-color-brand-primary": {
            "label": {
                "en-GB": "Primary colour",
                "de-DE": "Primärfarbe"
            },
            "type": "color",
            "value": "#008490",
            "editable": true,
            "block": "themeColors",
            "tab": "colors",
            "order": 100
        },
        ...
    }
}
```

When you don´t specify a tab then it will be shown in the main tab.

* Added Twig Filter `replace_recursive` for editing values in nested Arrays. This allows for editing
js options in Twig:

```twig
{% set productSliderOptions = {
    productboxMinWidth: sliderConfig.elMinWidth.value ? sliderConfig.elMinWidth.value : '',
    slider: {
        gutter: 30,
        autoplayButtonOutput: false,
        nav: false,
        mouseDrag: false,
        controls: sliderConfig.navigation.value ? true : false,
        autoplay: sliderConfig.rotate.value ? true : false
    }
} %}

{% block element_product_slider_slider %}
    <div class="base-slider"
         data-product-slider="true"
         data-product-slider-options="{{ productSliderOptions|json_encode }}">
    </div>
{% endblock %}
```

Now the variable can be overwritten with `replace_recursive`:

```twig
{% block element_product_slider_slider %}
    {% set productSliderOptions = productSliderOptions|replace_recursive({
        slider: {
            mouseDrag: true
        }
    }) %}

    {{ parent() }}
{% endblock %}
```

* Added basic captcha support to the storefront
  * Routes annotated with `@Captcha` will now require all active captchas to be valid
  * Captchas may be registered using the `shopware.storefront.captcha` tag and need to extend the `AbstractCaptcha` class
* Added `HoneypotCaptcha`
  * This captcha checks wether a form field hidden from the user was filled out and stops the request if that's the case
  * The `HoneypotCaptcha` is active by default
* If you use the `widgets.search.pagelet` route in your template, you have to replace this with `widgets.search.pagelet.v2`:
  * Before: `url('widgets.search.pagelet', { search: page.searchTerm })`
  * After: `url('widgets.search.pagelet.v2')`
* It is no longer possible to send requests against the `sales-channel-api` with the `HttpClient`. You have to use the `StoreApiClient` for this:
    * before: 
        ```javascript
        import Plugin from 'src/plugin-system/plugin.class';
        import HttpClient from 'src/service/http-client.service';
        
        export default class MyStorefrontPlugin extends Plugin {
            
            init() {
                this.client = new HttpClient();
                this.client.get('sales-channel-api-route', response => {})
            }
        }
        ```
    * after:
        ```javascript
        import Plugin from 'src/plugin-system/plugin.class';
        import StoreApiClient from 'src/service/store-api-client.service';
        
        export default class MyStorefrontPlugin extends Plugin {
            
            init() {
                this.client = new StoreApiClient();
                this.client.get('sales-channel-api-route', response => {});
            }
        }
        ```

* Added block `component_head_analytics_tag_config` to `analytics.html.twig`

Refactorings
------------
### DAL Indexer refactoring

With 6.2 we have refactored the implementation of the indexers. We had to make this decision because of two problems:
1. the indexers were interdependent so that they always had to be executed one after the other and never in parallel. 
2. many indexers were not designed to be triggered by message queue and therefore ran into mysql deadlocks or indexed wrong data.

#### Consequences
This now has the following consequences:
1. all indexers in the shopware core have been marked as deprecated and will be removed in 6.3. Accordingly, new indexers have been implemented.
2. if you have called the indexers, please use the new indexers. The old indexers are still available in the system, but without source code. 
3. if you have implemented your own indexer, you have to adapt it to the new system - as described below.
4. if you are using DAL fields, which were previously filled automatically by an indexer, you will now have to write your own indexer - we have provided classes for this purpose. The following DAL fields are affected:
    4.1 `\Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyIdField`
    4.2 `\Shopware\Core\Framework\DataAbstractionLayer\Field\TreeLevelField`
    4.3 `\Shopware\Core\Framework\DataAbstractionLayer\Field\TreePathField`
    4.4 `\Shopware\Core\Framework\DataAbstractionLayer\Field\ChildCountField`

#### Function of an indexer
The new indexers work as follows:
1. each indexer extends abstract `Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer`
2. each indexer takes care of indexing a whole entity
3. if several data on an entity need to be indexed, a single indexer takes care of the successive updating of the data
4. an event is thrown at the end of the indexer so that plugins can subscribe to the event to index additional data for this entity


#### The new base class
The base `Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer` class looks as follows:
```php

abstract class EntityIndexer
{
    /**
     * Returns a unique name for this indexer. This function is used for core updates
     * if a indexer has to run after an update.
     */
    abstract public function getName(): string;

    /**
     * Called when a full entity index is required. This function should generate a list of message for all records which
     * are indexed by this indexer.
     */
    abstract public function iterate($offset): ?EntityIndexingMessage;

    /**
     * Called when entities are updated over the DAL. This function should react to the provided entity written events
     * and generate a list of messages which has to be processed by the `handle` function over the message queue workers.
     */
    abstract public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage;

    /**
     * Called over the message queue workers. The messages are the generated messages
     * of the `self::iterate` or `self::update` functions.
     */
    abstract public function handle(EntityIndexingMessage $message): void;
}
```

The `iterate` and `update` functions are intended to trigger indexing. The `iterate` is triggered on a full index and the `update` when an entity is written in the system.
In both cases, an `EntityIndexingMessage` can be returned which is then either handled by the message queue or directly. 

#### Example indexer
The following `ProductIndexer` is intended to illustrate once again how such an indexer works. This indexer can be used as a template. Only the entity that is handled must be exchanged here.

```php
<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer;

use Shopware\Core\Content\Product\Events\ProductIndexerEvent;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ProductIndexer extends EntityIndexer
{
    /** @var IteratorFactory */
    private $iteratorFactory;

    /** @var EntityRepositoryInterface */
    private $repository;

    /** @var EventDispatcherInterface */
    private $eventDispatcher;

    public function getName(): string
    {
        return 'product.indexer';
    }

    public function iterate($offset): ?EntityIndexingMessage
    {
        $iterator = $this->iteratorFactory->createIterator($this->repository->getDefinition(), $offset);

        $ids = $iterator->fetch();

        // loop end? return null 
        if (empty($ids)) {
            return null;
        }

        return new EntityIndexingMessage($ids, $iterator->getOffset());
    }

    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        $updates = $event->getPrimaryKeys(ProductDefinition::ENTITY_NAME);

        if (empty($updates)) {
            return null;
        }

        // update essential data immediately - one example can be the available stock of an product

        return new EntityIndexingMessage($updates, null, $event->getContext());
    }

    public function handle(EntityIndexingMessage $message): void
    {
        $ids = $message->getData();

        if (empty($ids)) {
            return;
        }
    
        // update all required data

        $this->eventDispatcher->dispatch(new ProductIndexerEvent($ids, $message->getContext()));
    }
}
```

#### DAL fields with indexing
In case you have used fields from the DAL, which filled automatically by an indexer, you have now to trigger the data indexing for this fields by yourself. We provide updater classes which you call from your indexer.
However, we have adapted the previous indexers so that they will continue to update your entities automatically during the 6.2 version. If you have started the indexing process yourself, you can set a flag to prevent the old indexing process from working for your entity.

* `\Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyIdField` 
    * Can be updated by `\Shopware\Core\Framework\DataAbstractionLayer\Indexing\ManyToManyIdFieldUpdater`
    * Old indexing can be disabled by `public function hasManyToManyIdFields(): bool { return false; }` in your entity definition
 
* `\Shopware\Core\Framework\DataAbstractionLayer\Field\TreeLevelField` and `\Shopware\Core\Framework\DataAbstractionLayer\Field\TreePathField` 
    * can be updated by `\Shopware\Core\Framework\DataAbstractionLayer\Indexing\TreeUpdater`
    * Old indexing can be disabled by `public function isTreeAware(): bool { return false; }` in your entity definition
    
* `\Shopware\Core\Framework\DataAbstractionLayer\Field\ChildCountField` 
    * can be updated by `\Shopware\Core\Framework\DataAbstractionLayer\Indexing\ChildCountUpdater`
    * Old indexing can be disabled by `public function isChildCountAware(): bool { return false; }` in your entity definition

Simply create an indexer as above, inject the service and call it when your entity is updated.

#### Seo Urls
If you have implemented your own seo urls in the system, you will have to initiate the indexing of these urls yourself in the future. For the 6.2 version we have implemented a flag which defines whether the old indexing process should continue to work.
To update the seo urls correctly you need an indexer which indexes the entity behind the seo url. If there is already an indexer for the entity in the core you can register yourself on the indexer event, otherwise you have to write your own indexer.
In the indexer you can use the `\Shopware\Core\Content\Seo\SeoUrlUpdater` service to generate the seo urls. Here is an example how to call it:

```php

<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\SeoUrlRoute;

use Shopware\Core\Content\Product\Events\ProductIndexerEvent;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Content\Seo\SeoUrlUpdater;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SeoUrlUpdateListener implements EventSubscriberInterface
{
    /**
     * @var SeoUrlUpdater
     */
    private $seoUrlUpdater;

    public function __construct(SeoUrlUpdater $seoUrlUpdater)
    {
        $this->seoUrlUpdater = $seoUrlUpdater;
    }

    public static function getSubscribedEvents()
    {
        return [
            ProductEvents::PRODUCT_INDEXER_EVENT => 'updateProductUrls',
        ];
    }

    public function updateProductUrls(ProductIndexerEvent $event): void
    {
        $this->seoUrlUpdater->update('frontend.detail.page', $event->getIds());
    }
}
``` 

To disable the old indexing process for a seo url route you have to set the flag `\Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig::$supportsNewIndexer`. This is easily done in the `getConfig` method of your `SeoUrlRoute` class.

```php
public function getConfig(): SeoUrlRouteConfig
{
    return new SeoUrlRouteConfig(
        $this->productDefinition,
        self::ROUTE_NAME,
        self::DEFAULT_TEMPLATE,
        true,
        true // disable old indexing
    );
}
```

#### MySQL Deadlocks
Since the new indexers are now designed to be processed in parallel via the message queue, MySQL deadlocks can quickly occur when the same table is written by different processes. For this we have provided the helper class `\Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery`.
This offers various possibilities to avoid a MySQL deadlock:

```php

$query = new RetryableQuery(
    $this->connection->prepare('UPDATE product SET active = :active WHERE id = :id')
);

$query->execute(['id' => $id, 'active' => 1]);

$query = $this->connection->createQueryBuilder();
$query->update('...');
RetryableQuery::executeBuilder($query);

RetryableQuery::retryable(function() {
    $this->connection->executeUpdate('...');
});
```
