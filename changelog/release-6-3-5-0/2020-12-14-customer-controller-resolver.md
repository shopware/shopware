---
title: Customer controller resolver
issue: NEXT-12348
---
# Core
* Added `CustomerValueResolver` class at `Shopware\Core\Checkout\Customer`, for resolving a customer in the SaleChannelContext, it's required LoginRequired and SalesChannelContext
* Added `Customer $customer` parameter in store api routes. The parameter will be required in 6.4. At the moment the parameter is commented out in the `*AbstractRoute`, but the following parameters are already passed:
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
___
# Storefront
* Changed controller signature to inject customer over new controller resolver.
___
# Upgrade Information

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
