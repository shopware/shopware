---
title: Add new CartTaxDisplay rule condition
issue: NEXT-18686
author: Krispin Lütjann
author_email: k.luetjann@shopware.com
author_github: King-of-Babylon
---
# Core
* Added the new condition rule `Checkout/Cart/Rule/CartTaxDisplayRule.php`
___
# Administration
*  Added the new component `sw-condition-cart-tax-display`:
    * `src/app/component/rule/condition-type/sw-condition-cart-tax-display/index.js`
    * `src/app/component/rule/condition-type/sw-condition-cart-tax-display/sw-condition-cart-tax-display.html.twig`
* Added the new rule condition `cartTaxDisplay` to the `condition-type-data-provider.decorator`
