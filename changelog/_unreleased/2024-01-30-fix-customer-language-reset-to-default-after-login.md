---
title: Fix customer language reset to default after login
issue: NEXT-32024
---
# Core
* Changed `\Shopware\Core\Checkout\Customer\SalesChannel\AccountService::loginByCustomer` to not update customer's language after login.
