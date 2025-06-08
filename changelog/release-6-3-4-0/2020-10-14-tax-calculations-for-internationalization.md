---
title: Tax calculations for internationalization
issue: NEXT-11279
---
# Administration
* Added `tax_type` and `tax_id` fields to `shipping_method` table
* Added 3 types of shipping cost tax
         `TAX_TYPE_AUTO`
         `TAX_TYPE_FIXED`
         `TAX_TYPE_HIGHEST`
* Added method `highestRate` to `Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection.php` to get the tax rule that has the highest tax rate
* Added method `getHighestTaxRule` to `Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection` to get the tax rule from the highest tax rate
* Changed method `calculateShippingCosts` to calculate shipping costs based on 3 types of tax
