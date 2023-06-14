---
title: Salutation change is not possible in the storefront
issue: NEXT-27106
---
# Core
* Changed method `change` of class `Shopware\Core\Checkout\Customer\SalesChannel\ChangeCustomerProfileRoute` to remove `account_type` in the request if the value of this key is empty
* Changed method `register` of class `Shopware\Core\Checkout\Customer\SalesChannel\RegisterRoute` to remove `account_type` in the request if the value of this key is empty
