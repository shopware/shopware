---
title: Fix encoder
issue: NEXT-25177
author: Oliver Skroblin
author_email: o.skroblin@shopware.com
---
# Core
* Removed unused `CurrencyEntity::$shippingMethodPrices` property.
* Removed unused `ShippingMethodPriceEntity::$currency` property.
* Added `PaymentMethodDefinition::shortName` field mapping
* Added `SeoUrlDefinition::isValid` field mapping
* Added `SeoUrlDefinition::error` field mapping

___
# API
* Changed `\Shopware\Core\System\SalesChannel\Api\StructEncoder` to only encode entity properties which are mapped in the entity definition.

___
# Upgrade Information
## Only mapped properties encoded
The `\Shopware\Core\System\SalesChannel\Api\StructEncoder` now only encodes entity properties which are mapped in the entity definition.  If you have custom code which relies on the encoder to encode properties which are not mapped in the entity definition, you need to adjust your code to map these properties in the entity definition.
