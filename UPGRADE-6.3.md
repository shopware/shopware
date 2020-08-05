UPGRADE FROM 6.2.x to 6.3
=======================

Table of contents
----------------

* [Core](#core)
* [Administration](#administration)
* [Storefront](#storefront)
* [Refactorings](#refactorings)

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
These assets are prefixed in dependency injection with `shopware.asset.{ADAPTER_NAME}`
    * `shopware.asset.public`
    * `shopware.asset.theme`
    * `shopware.asset.asset`

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
