---
title: sw-currency-id header always overrides context values
---
# Core
* Added `\Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters::__construct(overwriteCurrencyId)` parameter to force overwrite of the currency id.
* Changed `\Shopware\Core\Framework\Routing\SalesChannelRequestContextResolver::resolve()` to always set the overwrite currency id from the request header.
* Changed `\Shopware\Core\System\SalesChannel\Context\SalesChannelContextService::get()` to always overwrite the currency id if the overwrite currency parameter is set.
___
# Upgrade Information
## Product weight precision
The database column `product.weight` now uses `DECIMAL(15,6)` instead of `DECIMAL(10,3)` to keep gram-based measurements accurate when values are stored in kilograms.
