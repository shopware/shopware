---
title: Add isGuestCustomer rule condition
issue: NEXT-13472
author: Krispin Lütjann
author_email: k.luetjann@shopware.com
author_github: King-of-Babylon
---
# Core
* Added the new condition rule `Checkout/Customer/Rule/IsGuestCustomerRule.php`
___
# Administration
*  Added the new component `sw-condition-is-guest`:
    * `src/app/component/rule/condition-type/sw-condition-is-guest/index.js`
    * `src/app/component/rule/condition-type/sw-condition-is-guest/sw-condition-is-guest.html.twig`
* Added the new rule condition `customerIsGuest` to the `condition-type-data-provider.decorator`
