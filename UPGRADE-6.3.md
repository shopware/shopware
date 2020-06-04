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

* Deprecated configuration `api.allowed_limits` in `src/Core/Framework/DependencyInjection/Configuration.php`
* Removed deprecations:
    * Removed deprecated property `allowedLimits` and method `getAllowedLimits` in `Shopware\Core\Framework\DataAbstractionLayer\Search/RequestCriteriaBuilder.php`
    * Removed deprecated configuration `api.allowed_limits` in `src/Core/Framework/Resources/config/packages/shopware.yaml`
    * Removed class `Shopware\Core\Framework\DataAbstractionLayer\Exception\DisallowedLimitQueryException`

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
* Added `Criteria $criteria` parameter in store api routes. The parameter will be required in 6.4. At the moment the parameter is commented out in the `*AbstractRoute`, but it is already passed. If you decorate on of the following routes, you have to change the your sources as follow:
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
            // remove this sources with, 6.4.0. The criteria will be required in this version
            if (!$criteria) {
                $criteria = $this->requestCriteriaBuilder->handleRequest($request, new Criteria(), $this->customerDefinition, $context->getContext());
            }
        }
        ```
* Removed the Vue event `inline-edit-assign` from `onClickCancelInlineEdit` method in `src/Administration/Resources/app/administration/src/app/component/data-grid/sw-data-grid/index.js`
    * This event is responsible for assigning the value of an inline-edit field of the data grid, which should not happen when the inline-edit is being canceled by the user.
    * In order to react to saving or cancelling the inline-edit of the `sw-data-grid`, use the `inline-edit-save` and `inline-edit-cancel` events.
* Deprecated data fetching methods in `ApiService` class, use the repository class for data fetching instead, see `CHANGELOG-6.3.md` file for a detailed overview
* Refactored `worker-notification-listener`
    * Removed constructor parameter `loginService`
    * Changed type of `queue` parameter of notification middleware function. It now contains an instance of the  `EntityCollection` class `src/core/data-new/entity-collection.data.js`.
    * Changed type of `entry` parameter of notification middleware function. It now contains an instance of the `Entity` class `src/core/data-new/entity.data.js`.
    * Removed parameter `response` of notification middleware function.

Storefront
--------------

Refactorings
------------
