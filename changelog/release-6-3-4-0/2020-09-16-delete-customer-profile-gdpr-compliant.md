---
title:  Delete customer profile GDPR compliant   
issue: NEXT-10194
---
# Core
* Added new `\Shopware\Core\Checkout\Customer\SalesChannel\DeleteCustomerRoute` class to allow deleting the customer using the store-api with the url DELETE `/store-api/v3/account/customer`
* Added new `\Shopware\Core\Checkout\Customer\Event\CustomerDeletedEvent` class to allow listening an event when customer deleted success
___
# Storefront
* Added new method `deleteProfile` to the `src/Storefront/Controller/AccountProfileController.php` controller
* Added 2 new blocks `page_account_profile_delete_account` and `page_account_delete_account_confirm_modal` to the `src/Storefront/Resources/views/storefront/page/account/profile/index.html.twig` template
