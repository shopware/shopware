---
title: Add cart position price rule condition
issue: NEXT-19470
author: Krispin Lütjann
author_email: k.luetjann@shopware.com 
author_github: King-of-Babylon
---
# Core
* Added the new condition rule `Checkout/Cart/Rule/CartPositionPriceRule.php`
___
# Administration
*  Added the new component `sw-condition-cart-position-price`:
    * `src/app/component/rule/condition-type/sw-condition-cart-position-price/index.js`
    * `src/app/component/rule/condition-type/sw-condition-cart-position-price/sw-condition-cart-position-price.html.twig`
* Added the new rule condition `cartPositionPrice` to the `condition-type-data-provider.decorator`
* Changed `sw-settings-shipping-price-matrix` to make the rule select of an empty matrix rule assignment aware
