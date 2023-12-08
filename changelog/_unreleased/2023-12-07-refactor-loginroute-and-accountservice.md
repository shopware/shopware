---
title: Refactor LoginRoute and AccountService
issue: NEXT-32258
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Core
* Added method `Shopware\Core\Checkout\Customer\SalesChannel\AccountService::loginByCredentials`
* Changed `Shopware\Core\Checkout\Customer\SalesChannel\LoginRoute` and moved login logic into the `AccountService`
* Deprecated method `Shopware\Core\Checkout\Customer\SalesChannel\AccountService::login` use `AccountService::loginByCredentials` or `AccountService::loginById` instead
* Deprecated unused constant `Shopware\Core\Checkout\Customer\CustomerException::CUSTOMER_IS_INACTIVE` and unused method `Shopware\Core\Checkout\Customer\CustomerException::inactiveCustomer`
