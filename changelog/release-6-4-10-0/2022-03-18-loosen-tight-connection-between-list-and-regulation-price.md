---
title: Loosen tight connection between list and regulation price
issue: NEXT-19804
author: Ramona Schwering
author_email: r.schwering@shopware.com
author_github: leichteckig
---
# Storefront
* Changed the following files to move the regulation price out of list price checks
  * `src/Storefront/Resources/views/storefront/component/product/card/price-unit.html.twig`
  * `src/Storefront/Resources/views/storefront/component/product/block-price.html.twig`
  * `src/Storefront/Resources/views/storefront/page/product-detail/buy-widget-price.html.twig`
  * `src/Storefront/Resources/views/storefront/component/buy-widget/buy-widget-price.html.twig`
