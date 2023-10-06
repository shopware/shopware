---
title: Add domainId to `sales_channel_api_context`.`payload`
issue: NEXT-21526
---
# Core
* Added setter method for `domainId` property at `Shopware\Core\System\SalesChannel\SalesChannelContext.php`
* Added `domainId` to payload when save data to `sales_channel_api_context` at `Shopware\Core\Checkout\Customer\SalesChannel\RegisterRoute::register()`
