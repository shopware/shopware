---
title: Fix tax rounding errors for percentage price calculation
issue: #2938
author: Max Stegmeyer
---
# Core
* Changed `Shopware\Core\Checkout\Cart\Price\PercentagePriceCalculator` to use percentages of existing tax calculations instead of recalculating a quantity price, which may result in different taxes than expected.
* Changed `Shopware\Core\Checkout\Cart\LineItem\LineItemQuantitySplitter` to not recalculate taxes for split line items, so they are not rounded for further calculations.
___
# Next Major Version Changes
## Tax Calculation for percentage discounts / surcharges, e.g. promotions
Taxes of percentage prices are not recalculated anymore, but use the existing tax calculation of the referenced line items.
This prevents rounding errors when calculating taxes for percentage prices.
