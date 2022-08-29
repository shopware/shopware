---
title: Fix display ampersand in checkout process and cart
issue: NEXT-20299
author: Michel Bade
author_email: m.bade@shopware.com
___
# Storefront
* Changed `src/Storefront/Resources/views/storefront/component/checkout/offcanvas-item.html.twig` to output label string of product as raw
* Changed `src/Storefront/Resources/views/storefront/page/checkout/checkout-item.html.twig` to output name string of product as raw
* Changed `src/Storefront/Resources/views/storefront/component/line-item/element/label.html.twig` to output label string of product as raw
* Changed `src/Storefront/Resources/views/storefront/page/checkout/checkout-aside-item.html.twig` to output label string of product as raw
