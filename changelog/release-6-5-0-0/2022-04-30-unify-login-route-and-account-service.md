---
title: Unify login route and account service
issue: NEXT-24324
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Core
* Added `Shopware\Core\Checkout\Customer\Exception\CustomerOptinNotCompletedException` exception which replaces the `Shopware\Core\Checkout\Customer\Exception\InactiveCustomerException` and will only be thrown if the opt in has not been completed, use the error snippet `account.doubleOptinAccountAlert` starting from Shopware 6.6.0.0
* Deprecated `Shopware\Core\Checkout\Customer\Exception\InactiveCustomerException` use `Shopware\Core\Checkout\Customer\Exception\BadCredentialsException` or `Shopware\Core\Checkout\Customer\Exception\CustomerOptinNotCompletedException` instead
___
# Upgrade Information
## Double OptIn customers will be active by default
If the double opt in feature for the customer registration is enabled the customer accounts will be set active by default starting from Shopware 6.6.0.0. The validation now only considers if the customer has the double opt in registration enabled, i.e. the database value `customer.double_opt_in_registration` equals `1` and if there exists an double opt in date in `customer.double_opt_in_confirm_date`.
