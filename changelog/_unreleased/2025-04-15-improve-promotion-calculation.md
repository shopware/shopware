---
title: Improve promotion calculation
issue: #8329
---
# Core
* Changed `getTotalPriceAmount` and `getUnitPriceAmount` method visibilities to public in `src/Core/Checkout/Cart/Price/Struct/PriceCollection.php`
* Added new `buildCollectionRules` method in `src/Core/Checkout/Cart/Tax/PercentageTaxRuleBuilder.php`
* Changed `getMatchingItems` in `src/Core/Checkout/Promotion/Cart/Discount/ScopePackager/CartScopeDiscountPackager.php`. Will only split items by quantity, if advanced rules and apply key not "ALL"
* Added new method `findCodeById` in `src/Core/Checkout/Promotion/Cart/CartPromotionsDataDefinition.php`
___
# API
*
___
# Administration
*
___
# Storefront
*
