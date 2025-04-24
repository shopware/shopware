---
title: Improve promotion calculation
issue: #8329
---
# Core
* Changed `getTotalPriceAmount` and `getUnitPriceAmount` method visibilities to public in `\Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection`
* Added new `buildCollectionRules` method in `\Shopware\Core\Checkout\Cart\Tax\PercentageTaxRuleBuilder`. No `\Shopware\Core\Checkout\Cart\Tax\CalculatedPrice` is needed, just the total price and `\Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection`.
* Changed `getMatchingItems` in `\Shopware\Core\Checkout\Promotion\Cart\Discount\ScopePackager\CartScopeDiscountPackager`. Will only split items by quantity, if discount value `considerAdvancedRules` is `true` and `applierKey` is not "ALL"
___
# Upgrade Information
## New public methods for performance improvements
### PriceCollection
If you use the `\Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection` `sum` function for a single value (e.g. `getTotalPrice`), you can now use the new public function `getUnitPriceAmount` or `getTotalPriceAmount` for performance reasons.
### PercentageTaxRuleBuilder
It isn't needed anymore to create a CalculatedPrice object. With the new method only a collection and total price is needed.
