---
title: Implement cash rounding
issue: NEXT-10004
author: Oliver Skroblin
author_email: o.skroblin@shopware.com 
author_github: Oliver Skroblin
---
# Core
* Added new required fields `item_rounding` and `total_rounding` field to `order` and `currency` entity
* Added new `currency_country_rounding` which contains country specific cash rounding configuration
* Added new `CashRoundingField` which stores the cash rounding in `order`, `currency` and `currency_country_rounding`
* Added new `CashRoundingConfig` class which will be decoded by the corresponding `CashRoundingField`
* Removed `RoundingInterface` and `Rounding` class, use `CashRounding` class instead
* Changed `CalculatedTaxCollection::round` parameter, the function requires now a `CashRounding` and `CashRoundingConfig` 
* Deprecated `Context::getCurrencyPrecision`, use `Context::getRounding` instead
* Removed `currencyPrecision` parameter of `Context::__construct()`
* Added `itemRounding` and `totalRounding` to `SalesChannelContext`
* Added `AbstractElasticsearchDefinition::extendDocuments` which allows to add not related entity data to an indexed document
* Fixed currency price indexing and usage in elasticsearch
* Changed `__construct` of all price definitions: `AbsolutePriceDefinition`, `PercentagePriceDefinition` and `QuantityPriceDefinition`, use `*Definition::create` instead
* Removed `precision` parameter of price definition, a specific precision for each definition is no longer supported
* Added `CartPrice::rawTotal` which contains the un-rounded total value
* Removed `\Shopware\Core\Checkout\Cart\Price\ReferencePriceCalculator`
* Removed `\Shopware\Core\Checkout\Cart\Tax\TaxRuleCalculator` use `TaxCalculator` instead
* Deprecated `\Shopware\Core\System\Currency\CurrencyEntity::$decimalPrecision` use `itemRounding` or `totalRounding` instead
