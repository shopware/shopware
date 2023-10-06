---
title: Wrap StorefrontController render call in ShopwareException
issue: NEXT-27284
---
# Storefront
* Added `StorefrontException` class in `Shopware\Storefront\Controller\Exception`.
* Changed `renderView` method in `Shopware\Storefront\Controller\StorefrontController` to wrap render view in domain exception.
