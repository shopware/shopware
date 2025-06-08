---
title: Deprecate Sales Channel API
issue: NEXT-10706
---
# Core

* Deprecated following classes :
    * `Shopware\Core\Checkout\Cart\SalesChannel\SalesChannelChartController`
    * `Shopware\Core\Checkout\Cart\SalesChannel\SalesChannelCheckoutController`
    * `Shopware\Core\Checkout\Customer\SalesChannel\SalesChannelCustomerController`
    * `Shopware\Core\Content\Cms\SalesChannel\SalesChannelCmsPageController`
    * `Shopware\Core\Content\Newsletter\SalesChannel\SalesChannelNewsletterController`
    * `Shopware\Core\Content\Product\SalesChannel\CrossSelling\SalesChannelCrossSellingController`
    * `Shopware\Core\Framework\Api\Response\Type\SalesChannel\JsonApiType`
    * `Shopware\Core\Framework\Api\Response\Type\SalesChannel\JsonType`
    * `Shopware\Core\Framework\Routing\SalesChannelApiRouteScope`
    * `Shopware\Core\System\SalesChannel\Entity\SalesChannelApiController`
    * `Shopware\Core\System\SalesChannel\SalesChannel\SalesChannelApiSchemaController`
    * `Shopware\Core\System\SalesChannel\SalesChannel\SalesChannelContextController`

___
# API

* Deprecated Sales Channel API will be removed with 6.4.0.
    * Use the replacements routes from the Store-API 

___

# Upgrade Information

## Deprecation of the Sales Channel API

As we finished with the implementation of our new Store API, we are deprecating the old Sales Channel API. 
The removal is planned for the 6.4.0.0 release. Projects are using the current Sales Channel API can migrate on api route base. 
