---
title:  Delete customer profile GDPR compliant   
issue:  NEXT-10194    
flag :  FEATURE_NEXT_10077
---
# Core
* Added new `\Shopware\Core\Checkout\Customer\SalesChannel\DeleteCustomerRoute` class to allow deleting the customer using the store-api with the url DELETE `/store-api/v3/account/customer`
* Added new `\Shopware\Core\Checkout\Customer\Event\CustomerDeletedEvent` class to allow listening an event when customer deleted success
