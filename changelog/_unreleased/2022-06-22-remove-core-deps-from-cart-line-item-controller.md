---
title: Remove core dependencies from CartLineItemController
issue: NEXT-21967
author: Stefan Sluiter
author_email: s.sluiter@shopware.com
author_github: ssltg
---
# Storefront
* Removed `Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface` as a constructor argument from `Shopware\Storefront\Controller\CartLineItemController`
* Changed `Shopware\Storefront\Controller\CartLineItemController` to use `Shopware\Core\Content\Product\SalesChannel\AbstractProductListRoute`