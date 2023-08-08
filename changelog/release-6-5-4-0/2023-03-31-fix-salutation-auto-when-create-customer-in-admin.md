---
title: Fix salutation auto set default when create customer in admin
issue: NEXT-25264
---
# Administration
* Added computed `defaultSalutationId`, `salutationCriteria`, `salutationRepository` in to get default salutation
  * `sw-customer-create` component.
  * `sw-customer-detail` component.
  * `sw-order-new-customer-modal` component.
  * `sw-customer-detail-addresses` component.
* Changed `createdComponent` method in to set default salutation for customer
  * `sw-customer-create` component.
  * `sw-customer-detail` component.
  * `sw-order-new-customer-modal` component.
___
# Core
* Changed `Shopware\Core\Checkout\Customer\SalesChannel\ChangeCustomerProfileRoute::change` to set default to `salutationId`
* Changed `Shopware\Core\Checkout\Customer\SalesChannel\RegisterRoute::register` to set default to `salutationId`
* Changed `Shopware\Core\Checkout\Customer\SalesChannel\UpsertAddressRoute::upsert` to set default to `salutationId`
* Changed `Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressDefinition` to remove flag required with salutationId.
* Added `Shopware\Core\System\Salutation\SalutationSorter` to sort salutations
* Added subscribers to set salutation with default not specified
  * `Shopware\Core\Checkout\Order\Subscriber\OrderSalutationSubscriber`
  * `Shopware\Core\Checkout\Customer\Subscriber\CustomerSalutationSubscriber`
  * `Shopware\Core\Content\Newsletter\Subscriber\NewsletterRecipientSalutationSubscriber`
___
# Storefront
* Changed function `register` in `Shopware\Storefront\Controller\RegisterController` to remove `definition` `salutationId`.
* Changed `Shopware\Storefront\Page\Account\Login\AccountLoginPageLoader::load` to sort salutations by `salutation_key` not specified.
* Changed `Shopware\Storefront\Page\Account\Profile\AccountProfilePageLoader::load` to sort salutations by `salutation_key` not specified.
* Changed `Shopware\Storefront\Page\Address\Detail\AddressDetailPageLoader::load` to sort salutations by `salutation_key` not specified.
* Changed `storefront/component/address/address-personal.html.twig` to remove attribute `required` with `salutationId`.
