UPGRADE FROM 6.2.x to 6.3
=======================

# 6.3.5.1
## Api aware fields
So far, we have used a protection pattern on the entities, to define which fields are available through the APIs. This pattern has been used for the `/admin` API as well as for the `/sales-channel-api` and `/store-api`.
A field could previously be excluded from an API via the `Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReadProtected`. This has now changed as follows:

* Every field is enabled for the `/admin` API by default. This happens via the base `\Shopware\Core\Framework\DataAbstractionLayer\Field\Field` class where we add the flag by default for the `/admin` API.
* To make a field available in the `/store-api` as well, the flag can be overwritten and the correct source can be specified in the new flag.
* By default, no information for an entity is available in the `/store-api`. Only by adding the flag the data becomes visible.
* If no source is passed to the flag, the flag will use all sources as default.
* If a field should not be available via any API, the flag can be removed via `->removeFlag(ApiAware::class)`.

* Example, make field available to all APIs (`/admin` and `/store-api`)
```php
(new TranslatedField('description'))->addFlags(new ApiAware())
```

* Example, make field available in `/store-api` only
```php
(new TranslatedField('description'))->addFlags(new ApiAware(SalesChannelApiSource::class))
```

* Example, remove field from all APIs:
```php
(new StringField('handler_identifier', 'handlerIdentifier'))->removeFlag(ApiAware::class)
```

# 6.3.5.0
## Plugin acl - Use `enrichPrivileges` instead of `addPrivileges`
The current behaviour of adding privileges via plugins is deprecated for 6.4.0.0.
Instead of writing custom plugin privileges via `Shopware\Core\Framework\Plugin::addPrivileges()` right into the database, 
plugins now should override the new `enrichPrivileges()` method to add privileges on runtime.
This method should return an array in the following structure:

```php
<?php declare(strict_types=1);

namespace MyPlugin;

use Shopware\Core\Framework\Plugin;

class SwagTestPluginAcl extends Plugin
{
    public function enrichPrivileges(): array
    {
        return [
            'product.viewer' => [
                'my_custom_privilege:read',
                'my_custom_privilege:write',
                'my_other_custom_privilege:read',
                // ...
            ],
            'product.editor' => [
                // ...
            ],
        ];
    }
}
```

## Require CustomerEntity parameter in store api routes
* Added `CustomerEntity $customer` parameter in store api routes. The parameter will be required in 6.4. At the moment, the parameter is commented out in the `*AbstractRoute`, but it is already passed. If you decorate on of the following routes, you have to change your sources as follows:
    * Affected routes:
        * `Shopware\Core\Checkout\Customer\SalesChannel\AbstractAddWishlistProductRoute`
        * `Shopware\Core\Checkout\Customer\SalesChannel\AbstractChangeCustomerProfileRoute`
        * `Shopware\Core\Checkout\Customer\SalesChannel\AbstractChangeEmailRoute`
        * `Shopware\Core\Checkout\Customer\SalesChannel\AbstractChangePasswordRoute`
        * `Shopware\Core\Checkout\Customer\SalesChannel\AbstractChangePaymentMethodRoute`
        * `Shopware\Core\Checkout\Customer\SalesChannel\AbstractCustomerRoute`
        * `Shopware\Core\Checkout\Customer\SalesChannel\AbstractDeleteAddressRoute`
        * `Shopware\Core\Checkout\Customer\SalesChannel\AbstractDeleteCustomerRoute`
        * `Shopware\Core\Checkout\Customer\SalesChannel\AbstractListAddressRoute`
        * `Shopware\Core\Checkout\Customer\SalesChannel\AbstractMergeWishlistProductRoute`
        * `Shopware\Core\Checkout\Customer\SalesChannel\AbstractRemoveWishlistProductRoute`
        * `Shopware\Core\Checkout\Customer\SalesChannel\AbstractSwitchDefaultAddressRoute`
        * `Shopware\Core\Checkout\Customer\SalesChannel\AbstractUpsertAddressRoute`
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
        use Shopware\Core\Checkout\Customer\CustomerEntity;

        /**
         * 
         * @LoginRequired()
         * @Route("/store-api/v{version}/account/customer", name="store-api.account.customer", methods={"GET"})
         */
        public function load(Request $request, SalesChannelContext $context, CustomerEntity $customer = null): CustomerResponse
        {
            // remove this code with, 6.4.0. The customer will be required in this version
            if (!$customer) {
                $customer = $context->getCustomer();
            }
        }
        ```
## Join Filter
With the new join filter logic, some queries of the DAL may return a different result. Each filter which is added to the criteria directly and contains a reference to a
to-many association, will lead to a sub-join with the corresponding filters inside.

If you add filters to a criteria which points to an to-many association field

So the following filters give two different results:

```
1: 
$criteria->addFilter(
    new AndFilter([
        new EqualsFilter('product.categories.name', 'test-category'),
        new EqualsFilter('product.categories.active', true)
    ])
);


2:
$criteria->addFilter(
    new EqualsFilter('product.categories.name', 'test-category')
);
$criteria->addFilter(
    new EqualsFilter('product.categories.active', true)
);

```

1: Returns all products assigned to the `test-category` category where `test-category` is also active.
2: Returns all products that are assigned to the `test-category` category AND have a category assigned that is active.

# 6.3.4.0
## Customer's sales channel context is restored after logged in
- Each customer now has a unique sales channel context, which means it will be shared across devices and browsers, including its cart.
- Which this change, when working with `SalesChannelContextPersister`, you should pass a 3rd parameter `sales_channel_id` and 4th parameter `customer_id` in `SalesChannelContextPersister::save()` to save customer's customer's context.
*  Customer email is not unique from all customers anymore, instead it will unique from other customers' email in a same sales channel.
*  The `$context` property in `Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerEmailUnique` is deprecated, using `SalesChannelContext $salesChannelContext` to get the context instead.
Use `import from src/module` instead of `import from 'module'`. However we discourage you to directly use imports of the administration's source in your plugins.
 Use the administration's open API through the global Shopware object.
## Usage of DBAL connection methods in migrations
For compatibility with main/replica database environments and blue green deployment,
it is important to use the correct methods of the DBAL connection in migrations.
Use `Doctrine\DBAL\Connection::executeUpdate` for these operations: `UPDATE|ALTER|BACKUP|CREATE|DELETE|DROP|EXEC|INSERT|TRUNCATE`
For everything else `Doctrine\DBAL\Connection::executeQuery` could be used.
Using `executeQuery` for the mentioned operations above is deprecated and will throw an exception with Shopware 6.4.0.0.
## Removed associations in customer group criteria
We have to remove the associations `salesChannels` and `customers` 
in these computed properties: `allCustomerGroupsCriteria` and `customerGroupCriteriaWithFilter`
which can be find in this component: `sw-settings-customer-group-list`.

The reason for this is that a shop with many customers can´t open the module. The response
is too heavy because all customers in the shop will be loaded. This can lead to a response 
timeout.

When you need the customer information then it would be good to fetch them in your plugin.
You should use a criteria object which only fetches a limited amount of customers.
## Upcoming config key change
Please be aware, that the configuration key `core.basicInformation.404Page` will be changed to
`core.basicInformation.http404Page` with the next major version v6.4.0.0. Please make sure that there are no references
to the old key `404Page` in your code before upgrading.

# 6.3.3.0
## Deprecation of the current sortings implementation

The current defined sortings in the service definition xml are deprecated for release **6.4.0.0** .

If you have defined custom sorting options in the service definition, please consider upgrading to the new logic via migration.

Before, custom sortings were handled by defining them as services and tagging them as `shopware.sales_channel.product_listing.sorting`:
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
Now it is possible to store custom sortings in the database `product_sorting` and its translatable label in `product_sorting_translation`
## Product listing filter handling
We optimized the product listing aggregation handling. 

In order to implement a filter for a product listing before, you had to register for the following events:
* `\Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent`
    * Adds the filter and aggregations to the criteria
* `\Shopware\Core\Content\Product\Events\ProductListingResultEvent`
    * Adds the filtered values to the result

### Before
```
class ExampleListingSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            ProductListingCriteriaEvent::class => 'handleRequest',
            ProductListingResultEvent::class => 'handleResult',
        ];
    }

    public function handleRequest(ProductListingCriteriaEvent $event)
    {
        $criteria = $event->getCriteria();

        $request = $event->getRequest();

        $criteria->addAggregation(
            new EntityAggregation('manufacturer', 'product.manufacturerId', 'product_manufacturer')
        );

        $ids = $this->getManufacturerIds($request);

        if (empty($ids)) {
            return;
        }

        $criteria->addPostFilter(new EqualsAnyFilter('product.manufacturerId', $ids));
    }

    public function handleResult(ProductListingResultEvent $event)
    {
        $event->getResult()->addCurrentFilter('manufacturer', $this->getManufacturerIds($event->getRequest()));
    }

    private function getManufacturerIds(Request $request): array
    {
        $ids = $request->query->get('manufacturer', '');
        $ids = explode('|', $ids);

        return array_filter($ids);
    }
}
```

### After
As we have now introduced a new mode for the filters, where the filters have been further reduced with each filtering, we have simplified the system.
For this, the event `\Shopware\Core\Content\Product\Events\ProductListingCollectFilterEvent` was introduced, where every developer can specify the meta data for a filter. 
The handling, if and how a filter is added, is done by the core.

```
class ExampleListingSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            ProductListingCollectFilterEvent::class => 'addFilter'
        ];
    }

    public function handleRequest(ProductListingCollectFilterEvent $event)
    {
        $filters = $event->getFilters();
        
        $ids = $this->getManufacturerIds($request);

        $filter = new Filter(
            //unique name of the filter
            'manufacturer',
            
            // defines if this filter is active
            !empty($ids),
            
            // defines aggregations behind a filter. Sometimes a filter contains multiple aggregations like properties
            [new EntityAggregation('manufacturer', 'product.manufacturerId', 'product_manufacturer')],
            
            // defines the DAL filter which should be added to the criteria   
            new EqualsAnyFilter('product.manufacturerId', $ids),
            
            // defines the values which will be added as currentFilter to the result
            $ids
        );

        $filters->add($filter);
    }

    private function getManufacturerIds(Request $request): array
    {
        $ids = $request->query->get('manufacturer', '');
        $ids = explode('|', $ids);

        return array_filter($ids);
    }
}
```
## Entity Repository Autowiring

The DAL entity repositories can now be injected into your services using autowiring. Necessary for this to work
(apart from having your service configured for [autowiring](https://symfony.com/doc/current/service_container/autowiring.html) generally)
are:
- The type of the parameter. It needs to be `EntityRepositoryInterface`
- The name of the variable. It must be the same as the id of the service in the DIC, written in `camelCase` instead of `snake_case`, followed by the word `Repository`.

So for example, a media_thumbnail repository (id `media_thumbnail.repository`) would be requested (and injected) like this:
```php
public function __construct(EntityRepositoryInterface $mediaThumbnailRepository) {}
```
## Write protection of `StateMachineStateField` was removed
The `StateMachineStateField` does not have a write-protection by default anymore. Instead, the scopes which are allowed
to write the field directly have to be given as a constructor parameter of the `StateMachineStateField` class.
## verifyUserToken() method
The verifyUserToken method was available nearly identical in multiple locations.
It has now been integrated into the loginService.js. In case you need to verify a User you can get an Access token
by calling loginService.verifyUserToken(userPassword) and provide the current user's password, the username will be automatically 
fetched from the session.
## `name` attribute of `ProductFeatureSetTranslationDefinition` will be non-nullable

With [NEXT-11000](https://issues.shopware.com/issues/NEXT-11000), the `name` attribute in
[ProductFeatureSetTranslationDefinition](https://github.com/shopware/platform/blob/master/src/Core/Content/Product/Aggregate/ProductFeatureSetTranslation/ProductFeatureSetTranslationDefinition.php)
was marked non-nullable. This change is also implemented on database-level with
[Migration1601388975RequireFeatureSetName.php](https://github.com/shopware/platform/blob/master/src/Core/Migration/Migration1601388975RequireFeatureSetName.php).
For blue-green deployment compatibility, the now non-nullable field will have an empty string as default value.
The upcoming **6.4.0.0** release will contain major **breaking changes** to the payment and shipping method selection templates in the storefront.
The modal to select payment or shipping methods was removed entirely.
Instead, the payment and shipping methods will be shown instantly up to a default maximum of `5` methods.
All other methods will be hidden inside a JavaScript controlled collapse.

The changes especially apply to the `confirm checkout` and `edit order` pages.

We refactored most of the payment and shipping method storefront templates and split the content up into multiple templates to raise the usability.

**Please review the changes on the `major` branch on GitHub.**  

## Breaking changes in upcoming v6.4.0.0 release:

`storefront/page/checkout/confirm/confirm-payment.html.twig`:
 * Renamed block `page_checkout_confirm_payment_current` to `page_checkout_change_payment_form`. This block will include the new component `storefront/component/payment/payment-form.html.twig` which will hold the contents.
 * Removed block `page_checkout_confirm_payment_current_image`.
 * Removed block `page_checkout_confirm_payment_current_text`.
 * Removed block `page_checkout_confirm_payment_invalid_tooltip`.
 * Removed block `page_checkout_confirm_payment_modal_toggle`.
 * Removed block `page_checkout_confirm_payment_modal`.
 * Removed block `page_checkout_confirm_payment_modal_body`.

`storefront/page/checkout/confirm/confirm-shipping.html.twig`:
 * Renamed block `page_checkout_confirm_shipping_current` to `page_checkout_change_shipping_form`. This block will include the new component `storefront/component/shipping/shipping-form.html.twig` which will hold the contents.
 * Moved content of block `page_checkout_confirm_shipping_form` to the new components.
 * Removed block `page_checkout_confirm_shipping_current_image`.
 * Removed block `page_checkout_confirm_shipping_current_text`.
 * Removed block `page_checkout_confirm_shipping_invalid_tooltip`.
 * Removed block `page_checkout_confirm_shipping_modal_toggle`.
 * Removed block `page_checkout_confirm_shipping_modal`.
 * Removed block `page_checkout_confirm_shipping_modal_body`.

`storefront/component/payment/payment-fields.html.twig`:
 * Moved content of block `component_payment_method` to its own new template `storefront/component/payment/payment-method.html.twig`.

Added following templates:
 * `storefront/component/payment/payment-form.html.twig`.
 * `storefront/component/payment/payment-method.html.twig`.
 * `storefront/component/shipping/shipping-form.html.twig`.
 * `storefront/component/shipping/shipping-fields.html.twig`.
 * `storefront/component/shipping/shipping-method.html.twig`.
 * `storefront/page/account/order/confirm-payment.html.twig`.
 * `storefront/page/account/order/confirm-shipping.html.twig`.

Removed following templates:
 * `storefront/page/account/order/payment.html.twig`.
 * `storefront/page/account/order/shipping.html.twig`.
 * `storefront/page/account/order/change-payment-modal.html.twig`.

## New handling to assign mail templates to business events

With the new event action module (`sw-event-action`) the user can configure which mail template will be sent for a business event. This makes other assignments superfluous:
* The assignment for mail templates inside the order module (when changing the order state) is no longer needed.
* The component `sw-order-state-change-modal-assign-mail-template` is deprecated for `tag:v6.0.0` and is not being rendered anymore from now on. Changes which have been made to this component will not be visible.
* The assignment of sales channels inside the mail template detail page is no longer needed.
* The select field inside the block `sw_order_state_change_modal_assign_mail_template_component` in `Resources/app/administration/src/module/sw-order/component/sw-order-state-change-modal/sw-order-state-change-modal.html.twig` was removed.
  * The twig block is still present but css or template extensions which rely on the field to be displayed may have to be adjusted.
  * A sales channel selection is only needed in order to send a test mail and has been added to the `sidebar` slot of `sw-mail-template-detail` component.

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

Others
------------
* All current administration users will be set to admin users due to the release of the acl system. Please check your user rights after update.
