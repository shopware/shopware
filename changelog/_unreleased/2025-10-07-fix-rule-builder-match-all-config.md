---
title: Fix rule builder match all configuration
issue: 12493
author: Lars Kemper
author_email: l.kemper@shopware.com
author_github: @larskemper
---
# Core
* Added `getConfig()` method to `LineItemInCategoryRule`, `LineItemPropertyRule` and `LineItemPurchasePriceRule` classes to use generic `RuleConfig`
* Changed `LineItemPurchasePriceRule` to use `type` field instead of `isNet` for net/gross price selection to align with generic `RuleConfig`
* Added migration `Migration1754398573ChangeAllLineItemsRuleValueType` to update line item purchase price rule condition values in existing rules
* Added `LineItemPerItemQuantityRule` class for matching line items based on individual quantity
* Removed `isMatchAny` operator option from `src/Core/System/Currency/Rule/CurrencyRule.php` rule condition
* Added `isMatchAny` operator option to rule configuration methods across multiple rule conditions: 
  - `src/Core/Checkout/Cart/Rule/CartHasDeliveryFreeItemRule.php`
  - `src/Core/Checkout/Cart/Rule/LineItemActualStockRule.php`
  - `src/Core/Checkout/Cart/Rule/LineItemClearanceSaleRule.php`
  - `src/Core/Checkout/Cart/Rule/LineItemCreationDateRule.php`
  - `src/Core/Checkout/Cart/Rule/LineItemDimensionHeightRule.php`
  - `src/Core/Checkout/Cart/Rule/LineItemDimensionLengthRule.php`
  - `src/Core/Checkout/Cart/Rule/LineItemDimensionVolumeRule.php`
  - `src/Core/Checkout/Cart/Rule/LineItemDimensionWeightRule.php`
  - `src/Core/Checkout/Cart/Rule/LineItemDimensionWidthRule.php`
  - `src/Core/Checkout/Cart/Rule/LineItemInCategoryRule.php`
  - `src/Core/Checkout/Cart/Rule/LineItemIsNewRule.php`
  - `src/Core/Checkout/Cart/Rule/LineItemListPriceRatioRule.php`
  - `src/Core/Checkout/Cart/Rule/LineItemListPriceRule.php`
  - `src/Core/Checkout/Cart/Rule/LineItemOfTypeRule.php`
  - `src/Core/Checkout/Cart/Rule/LineItemProductStatesRule.php`
  - `src/Core/Checkout/Cart/Rule/LineItemPromotedRule.php`
  - `src/Core/Checkout/Cart/Rule/LineItemPropertyRule.php`
  - `src/Core/Checkout/Cart/Rule/LineItemPurchasePriceRule.php`
  - `src/Core/Checkout/Cart/Rule/LineItemReleaseDateRule.php`
  - `src/Core/Checkout/Cart/Rule/LineItemStockRule.php`
  - `src/Core/Checkout/Cart/Rule/LineItemTotalPriceRule.php`
  - `src/Core/Checkout/Cart/Rule/LineItemUnitPriceRule.php`
  - `src/Core/Checkout/Promotion/Rule/PromotionCodeOfTypeRule.php`
___
# Administration
* Deprecated `sw-condition-line-item-in-category` component for version 6.8.0
* Deprecated `sw-condition-line-item-purchase-price` component for version 6.8.0
* Deprecated `sw-condition-is-net-select` component for version 6.8.0
___
# Upgrade Information
## Line Item Purchase Price Rule Changes
* The `LineItemPurchasePriceRule` now uses a `type` field instead of `isNet` boolean
* Migration `Migration1754398573ChangeAllLineItemsRuleValueType` will automatically update existing rule conditions
* New type options: `CartPrice::TAX_STATE_NET` ("net") and `CartPrice::TAX_STATE_GROSS` ("gross") for price type selection from
## New Line Item Per Item Quantity Rule 
* Added new `LineItemPerItemQuantityRule` for quantity-based line item matching
* This rule allows matching line items based on their individual quantity rather than total quantity
