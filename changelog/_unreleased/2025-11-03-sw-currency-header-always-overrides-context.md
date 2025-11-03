---
title: sw-currency-id header always overrides context values
---
# Core
* Added `\Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters::__construct(overwriteCurrencyId)` parameter to force overwrite of the currency id.
* Changed `\Shopware\Core\Framework\Routing\SalesChannelRequestContextResolver::resolve()` to always set the overwrite currency id from the request header.
* Changed `\Shopware\Core\System\SalesChannel\Context\SalesChannelContextService::get()` to always overwrite the currency id if the overwrite currency parameter is set.