---
title: Remain original Admin API sales channel source
issue: NEXT-39173
---
# Core
* Changed `Shopware\Core\Framework\Api\ControllerSalesChannelProxyController::setUpSalesChannelApiRequest` to use context admin source
* Added request attribute `ATTRIBUTE_CONTEXT_OBJECT` to the `\Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters` variable, which is passed to the `\Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface` in `\Shopware\Core\Framework\Routing\SalesChannelRequestContextResolver::resolve` method
