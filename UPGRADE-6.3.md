UPGRADE FROM 6.2.x to 6.3
=======================

# 6.3.2.0
## Deprecation of the Sales Channel API

As we finished with the implementation of our new Store API, we are deprecating the old Sales Channel API. 
The removal is planned for the 6.4.0.0 release. Projects are using the current Sales Channel API can migrate on api route base.

## HTTP Client for Store API
Use the HTTP client in your Javascript for calls to the Store API.

Example usage:
```javascript
import StoreApiClient from 'src/service/store-api-client.service';
const client = new StoreApiClient;
client.get('/store-api/v2/country', function(response) {
  console.log(response)
});
```
## Entity Foreign Key Resolver
There are currently systems that have performance problems with the `\Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityForeignKeyResolver`.
We have now created a solution for this, but we have to change the format of the return value of the different functions as follow:

### getAffectedDeleteRestrictions & getAffectedDeletes
* `EntityForeignKeyResolver::getAffectedDeleteRestrictions`
* `EntityForeignKeyResolver::getAffectedDeletes`

**before**
```
[
    [
        'pk' => '43c6baad756140d8aabbbca533a8284f'
        restrictions => [
            "order_customer" => array:2 [
                "cace68bdbca140b6ac43a083fb19f82b",
                "50330f5531ed485fbd72ba016b20ea2a",
            ]
            "order_address" => array:4 [
                "29d6334b01e64be28c89a5f1757fd661",
                "484ef1124595434fa9b14d6d2cc1e9f8",
                "601133b1173f4ca3aeda5ef64ad38355",
                "9fd6c61cf9844a8984a45f4e5b55a59c",
            ]
        ]
    ]
]
```

**after** 
```
[
    "order_customer" => array:2 [
        "cace68bdbca140b6ac43a083fb19f82b",
        "50330f5531ed485fbd72ba016b20ea2a",
    ]
    "order_address" => array:4 [
        "29d6334b01e64be28c89a5f1757fd661",
        "484ef1124595434fa9b14d6d2cc1e9f8",
        "601133b1173f4ca3aeda5ef64ad38355",
        "9fd6c61cf9844a8984a45f4e5b55a59c",
    ]
]
```

### getAffectedSetNulls
* `EntityForeignKeyResolver::getAffectedSetNulls`

**before**
```
[
    [
        'pk' => '43c6baad756140d8aabbbca533a8284f'
        restrictions => [
            'Shopware\Core\Content\Product\ProductDefinition' => [
                '1ffd7ea958c643558256927aae8efb07' => ['category_id'],
                '1ffd7ea958c643558256927aae8efb07' => ['category_id', 'main_category_id']
            ]
        ]
    ]
]
```               

**after**
```
[
    'product.manufacturer_id' => [
        '1ffd7ea958c643558256927aae8efb07',
        '1ffd7ea958c643558256927aae8efb07'
    ],
    'product.cover_id' => [
        '1ffd7ea958c643558256927aae8efb07'
        '1ffd7ea958c643558256927aae8efb07'
    ]
]
```
# 6.3

API
----
## Drop support of API version V1
With Shopware 6.3.0.0 we increased the API version to `v3` and therefore dropped the API version `v1` and removed all corresponding deprecations which where marked for the 6.3 version tag. This mainly affects deprecations which where made during the development of the 6.1 version. As we try to keep the downwards compatibility always one API version backwards, there are now two available API versions: `v3` and `v2`.

Core
----

* The `\Shopware\Core\System\Snippet\Files\SnippetFileInterface` is deprecated, please provide your snippet files in the right directory with the right name so shopware is able to autoload them.
Take a look at the `Autoloading of Storefront snippets` section in this guide: `Docs/Resources/current/30-theme-guide/40-snippets.md`, for more information.
After that you are able to delete your implementation of the `SnippetFileInterface`.
* Deprecated configuration `api.allowed_limits` in `src/Core/Framework/DependencyInjection/Configuration.php`
* Removed deprecations:
    * Removed deprecated property `allowedLimits` and method `getAllowedLimits` in `Shopware\Core\Framework\DataAbstractionLayer\Search/RequestCriteriaBuilder.php`
    * Removed deprecated configuration `api.allowed_limits` in `src/Core/Framework/Resources/config/packages/shopware.yaml`
    * Removed class `Shopware\Core\Framework\DataAbstractionLayer\Exception\DisallowedLimitQueryException`
* Added `CloneBehavior $behavior` parameter to `\Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface::clone`. This parameter will be introduced in 6.4.0.
    * If you implement an own class of EntityRepository, you have to change your clone function as follow:
    * Before:
    ```
    public function clone(string $id, Context $context, ?string $newId = null): EntityWrittenContainerEvent
    ```

    * After
    ```
    public function clone(string $id, Context $context, ?string $newId = null, CloneBehavior $behavior = null): EntityWrittenContainerEvent
    {
        // ...
        $affected = $this->versionManager->clone(
            $this->definition,
            $id,
            $newId,
            $context->getVersionId(),
            WriteContext::createFromContext($context),
            $behavior ?? new CloneBehavior()
        );
    
        // ...
    }
    ```
* Changed status code of `\Shopware\Core\System\SalesChannel\NoContentResponse` from `200` to `204`.
* Added two new arguments `$package` and `$cacheClearer` to constructor of `\Shopware\Storefront\Theme\ThemeCompiler`.
* Replaced Symfony `asset:install` command with a Flysystem compatible own implementation
* Added new filesystem adapters `shopware.filesystem.theme`, `shopware.filesystem.asset` and `shopware.filesystem.sitemap`. They can be configured to use external storages for saving of theme contents, bundle assets or sitemap.
* Added new argument `$package` to constructor of `\Shopware\Core\Content\Sitemap\Service\SitemapLister`.
* Deprecated config `shopware.cdn.url`. Use `shopware.filesystem.public.url` instead.
* Added new scss variable `sw-asset-theme-url` which refers to the theme asset url.
* If you subscribed to one of the following `\Shopware\Storefront\Event\RouteRequest\RouteRequestEvent` events, you must now extend the provided criteria instead of adding the query to the request:
    * Before
    ```php
    use Shopware\Storefront\Event\RouteRequest\OrderRouteRequestEvent;
    use Symfony\Component\EventDispatcher\EventSubscriberInterface;
    
    class MySubscriber implements EventSubscriberInterface
    {
        public static function getSubscribedEvents()
        {
            return [
                OrderRouteRequestEvent::class => 'listener'
            ];
        }
    
        public function listener(OrderRouteRequestEvent $event)
        {
            $query = $event->getStoreApiRequest()->query->get('associations');
            $query['lineItems']['associations']['product'] = [];
            $event->getStoreApiRequest()->query->set('associations', $query);
        }
    }
    ```
  
    * After
    ```php
    use Shopware\Storefront\Event\RouteRequest\OrderRouteRequestEvent;
    use Symfony\Component\EventDispatcher\EventSubscriberInterface;
    
    class MySubscriber implements EventSubscriberInterface
    {
        public static function getSubscribedEvents()
        {
            return [
                OrderRouteRequestEvent::class => 'listener'
            ];
        }
    
        public function listener(OrderRouteRequestEvent $event)
        {
            $event->getCriteria()->addAssociation('lineItems.product');
        }
    }
    ```
* Added `Criteria $criteria` parameter in store api routes. The parameter will be required in 6.4. At the moment, the parameter is commented out in the `*AbstractRoute`, but it is already passed. If you decorate on of the following routes, you have to change your sources as follows:
      * Affected routes:
          * `Shopware\Core\Checkout\Customer\SalesChannel\AbstractCustomerRoute`           
          * `Shopware\Core\Checkout\Order\SalesChannel\AbstractOrderRoute`                 
          * `Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute`       
          * `Shopware\Core\Checkout\Shipping\SalesChannel\AbstractShippingMethodRoute`     
          * `Shopware\Core\Content\Category\SalesChannel\AbstractNavigationRoute`          
          * `Shopware\Core\Content\Product\SalesChannel\Listing/AbstractProductListingRoute`
          * `Shopware\Core\Content\Product\SalesChannel\Search/AbstractProductSearchRoute` 
          * `Shopware\Core\Content\Seo\SalesChannel\AbstractSeoUrlRoute`                   
          * `Shopware\Core\System\Currency\SalesChannel\AbstractCurrencyRoute`             
          * `Shopware\Core\System\Language\SalesChannel\AbstractLanguageRoute`             
          * `Shopware\Core\System\Salutation\SalesChannel\AbstractSalutationRoute`         
      * Sources before:
          ```
          /**
           * @Route("/store-api/v{version}/account/customer", name="store-api.account.customer", methods={"GET"})
           */
          public function load(Request $request, SalesChannelContext $context): CustomerResponse
          {
              $criteria = $this->requestCriteriaBuilder->handleRequest(
                  $request, 
                  new Criteria(), 
                  $this->customerDefinition, 
                  $context->getContext()
              );
          }      
          ```
      * Sources after:
          ```
          use Shopware\Core\Framework\Routing\Annotation\Entity;
          
          /**
           * the below @Entity() annotation builds the criteria automatically for the current request
           * @Entity("customer")  
           * @Route("/store-api/v{version}/account/customer", name="store-api.account.customer", methods={"GET"})
           */
          public function load(Request $request, SalesChannelContext $context, Criteria $criteria = null): CustomerResponse
          {
              // remove this code with, 6.4.0. The criteria will be required in this version
              if (!$criteria) {
                  $criteria = $this->requestCriteriaBuilder->handleRequest($request, new Criteria(), $this->customerDefinition, $context->getContext());
              }
          }
          ```


* The behaviour when uninstalling a plugin has changed: `keepMigrations` now has the same value as `keepUserData` in `\Shopware\Core\Framework\Plugin\Context\UninstallContext` by default.
    * From now on migrations will be removed if the user data should be removed, and kept if the user data should be kept.
    * The `enableKeepMigrations()` function is no longer to be used and will be removed along with `keepMigrations()` in v6.4.0.
    * Please note: In case of a complete uninstall all tables should be removed as well. Please verify the uninstall method of your plugin complies with this.
* Adding custom sortings to the storefront is now supported in the administration
    * Before, custom sortings were handled by defining them as services and tagging them as `shopware.sales_channel.product_listing.sorting`:
    ```xml
    <service id="product_listing.sorting.name_ascending" class="Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingSorting">
        <argument>name-asc</argument>
        <argument>filter.sortByNameAscending</argument>
        <argument type="collection">
            <argument key="product.name">asc</argument>
        </argument>
        <tag name="shopware.sales_channel.product_listing.sorting" />
    </service>
    ```
    * Now it is possible to store custom sortings in the database `product_sorting` and its translatable label in `product_sorting_translation`
* Added validation deliverability in case purchase steps

* Deprecated providing an until timestamp as the last argument when running the `database:migrate` or `database:migrate-destructive` commands, use the --until option instead.
    * Before:
    ```
    bin/console database:migrate MyPlugin 1598339065
    ```
    
    * After
    ```
    bin/console database:migrate --until=1598339065 MyPlugin
    ```

* We have moved the logic for loading the detail page to Store-Api routes. The following extension points have been adapted for this:
    * Some services and struct classes moved from the `Shopware\Storefront` domain to the `Shopware\Core` domain. The public api are still the same but if you decorated one of the following classes you have to change your `extends` expression and the `decorates` definition in your services.xml:
        * `\Shopware\Storefront\Page\Product\Configurator\AvailableCombinationLoader` => `Shopware\Core\Content\Product\SalesChannel\Detail\AvailableCombinationLoader`
        * `\Shopware\Storefront\Page\Product\Configurator\ProductPageConfiguratorLoader` => `Shopware\Core\Content\Product\SalesChannel\Detail\ProductConfiguratorLoader`
        * `\Shopware\Storefront\Page\Product\CrossSelling\CrossSellingLoader` => `\Shopware\Core\Content\Product\SalesChannel\CrossSelling\AbstractProductCrossSellingRoute`
        * `\Shopware\Storefront\Page\Product\ProductLoader` => `Shopware\Core\Content\Product\SalesChannel\Detail\ProductDetailRoute`
    * Usage of the `\Shopware\Storefront\Page\Product\ProductLoader` are no longer recommend. To fetch the product data of a single product, use the `Shopware\Core\Content\Product\SalesChannel\Detail\ProductDetailRoute`
        * With this deprecation we also deprecated the `\Shopware\Storefront\Page\Product\ProductLoaderCriteriaEvent`. 
            * If you have subscribed to this event to extend the product detail page, replace the event with `\Shopware\Storefront\Page\Product\ProductPageCriteriaEvent`
            * If you have subscribed to this event to extend the listing quick view, replace the event with `\Shopware\Storefront\Page\Product\QuickView\MinimalQuickViewPageCriteriaEvent`  
    * As with the services, we have also moved some events from the storefront to the core. The public API of the events is the same. The following events can be replaced 1:1:
        * `\Shopware\Storefront\Page\Product\CrossSelling\CrossSellingLoadedEvent` => `\Shopware\Core\Content\Product\Events\ProductCrossSellingsLoadedEvent` instead
        * `\Shopware\Storefront\Page\Product\CrossSelling\CrossSellingProductCriteriaEvent` => `\Shopware\Core\Content\Product\Events\ProductCrossSellingCriteriaEvent` instead
        * `\Shopware\Storefront\Page\Product\CrossSelling\CrossSellingProductListCriteriaEvent` => `\Shopware\Core\Content\Product\Events\ProductCrossSellingIdsCriteriaEvent` instead
        * `\Shopware\Storefront\Page\Product\CrossSelling\CrossSellingProductStreamCriteriaEvent` => `\Shopware\Core\Content\Product\Events\ProductCrossSellingStreamCriteriaEvent` instead
        
Administration
--------------

* Removed LanguageStore
    * Use Context State instead
    * Replace `languageStore.setCurrentId(this.languageId)` with `Shopware.State.commit('context/setApiLanguageId', languageId)`
    * Replace `languageStore.getCurrentId()` with `Shopware.Context.api.languageId`
    * Replace `getCurrentLanguage` with the Repository
    * Removed `getLanguageStore`
    * Replace `languageStore.systemLanguageId` with `Shopware.Context.api.systemLanguageId`
    * Replace `languageStore.currentLanguageId` with `Shopware.Context.api.languageId`
    * Removed `languageStore.init`
    * Added mutation to Context State: `setApiLanguageId`
    * Added mutation to Context State: `resetLanguageToDefault`
    * Added getter to Context State: `isSystemDefaultLanguage`
* Refactored data fetching and saving in `sw-settings-documents` module
    * It now uses repositories for data handling instead of `State.getStore()`
    * See the `CHANGELOG-6.3.md` file for a detailed overview
* Removed the Vue event `inline-edit-assign` from `onClickCancelInlineEdit` method in `src/Administration/Resources/app/administration/src/app/component/data-grid/sw-data-grid/index.js`
    * This event is responsible for assigning the value of an inline-edit field of the data grid, which should not happen when the inline-edit is being canceled by the user.
    * In order to react to saving or cancelling the inline-edit of the `sw-data-grid`, use the `inline-edit-save` and `inline-edit-cancel` events.
* Deprecated data fetching methods in `ApiService` class, use the repository class for data fetching instead, see `CHANGELOG-6.3.md` file for a detailed overview
* Refactored `worker-notification-listener`
    * Removed constructor parameter `loginService`
    * Changed type of `queue` parameter of notification middleware function. It now contains an instance of the  `EntityCollection` class `src/core/data-new/entity-collection.data.js`.
    * Changed type of `entry` parameter of notification middleware function. It now contains an instance of the `Entity` class `src/core/data-new/entity.data.js`.
    * Removed parameter `response` of notification middleware function.
* Replace the component 'sw-settings-user-detail' with 'sw-users-permissions-user-detail'
* Replace the component 'sw-settings-user-create' with 'sw-users-permissions-user-create'
* Replace the component 'sw-settings-user-list' with 'sw-users-permissions-user-listing'
* When using `sw-custom-field-list` make sure you have set the `page`, `limit` and `total` prop
* Deprecated api services 
    * `cartSalesChannelService`: use `cartStoreApiService`
    * `checkOutSalesChannelService`: use `checkoutStoreService`
    * `salesChannelContextService`: use `storeContextService`
* When using `getCurrencyPriceByCurrencyId` in `sw-product-list/index.js` parameters must be changed from `(itemId, currencyId)` to `(currencyId, prices)`.
    * Before:
        ```
        <template v-for="currency in currencies"
                  :slot="`column-price-${currency.isoCode}`"
                  slot-scope="{ item }">
            {{ getCurrencyPriceByCurrencyId(item.id, currency.id).gross | currency(currency.isoCode) }}
        </template>
        ```
    * After:
        ```
        <template v-for="currency in currencies"
                  :slot="`column-price-${currency.isoCode}`"
                  slot-scope="{ item }">
            {{ getCurrencyPriceByCurrencyId(currency.id, item.price).gross | currency(currency.isoCode) }}
        </template>
        ```
* Added the following new components, to enable administration of essential characteristics
    * `sw-settings-product-feature-sets-modal`
    * `sw-settings-product-feature-sets-values-card`
    * `sw-settings-product-feature-sets-detail`
    * `sw-settings-product-feature-sets-list`
* Removed the `inheritance` header being set to `true` in the method `loadProduct` of the component `sw-product-detail`
* Removed unnecessary loading of `crossSelling` associations in the computed property `productCriteria` of the component `sw-product-detail`

Storefront
--------------

Refactorings
------------

# Asset System Refactoring

## Flysystem adapters
With 6.3 we have refactored the url handling of including resources like images, js, css etc. We have also created three new adapters: `asset` (plugin public files), `theme` (theme resources) and `sitemap`.
For comparability reason they inherit from the `public` filesytem. So after the update all new filesystem are using the config from public filesystem.
[See the updated documentation to how to configure all filesystems.](https://docs.shopware.com/en/shopware-platform-dev-en/how-to/use-s3-datastorage)
All file system configuration have now an `url` config option, this url will be used for url generation to the files.

## Usage of the Symfony asset
To unify the URL generation, we create a Symfony asset for each public filesystem adapter. This will build the correct URL with a version cache busting.
These assets are prefixed in dependency injection with `shopware.asset.{ADAPTER_NAME}`:  
*  `shopware.asset.public`
*  `shopware.asset.theme`
*  `shopware.asset.asset`

Example in PHP:
```php
// This is an example. Please use dependency injection
$publicAsset = $container->get('shopware.asset.public');

// Get the full url to the image
$imageUrl = $publicAsset->getUrl('folder/image.png');
```

Example in Twig:

```twig
{{ asset('folder/image.png', 'public') }
```

Example in SCSS

```scss
body {
  background: url("#{$sw-asset-theme-url}/bundles/storefront/assets/img/some-image.webp");
}
```

To access in scss the asset url, you can use the variable `$sw-asset-theme-url`

## Configuring `asset` and `theme` asset to other locations

Make sure you are using the correct asset package in the twig function `asset`.

* Themes: `{{ asset('folder/image.png', 'theme') }`
* Plugins: `{{ asset('folder/image.png', 'asset') }` or `{{ asset('folder/image.png', '@MyPluginName') }`
