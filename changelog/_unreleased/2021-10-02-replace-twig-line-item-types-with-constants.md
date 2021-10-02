---
title: Replace Twig LineItem types with constants
issue: NEXT-17683
author: Ioannis Pourliotis
author_email: dev@pourliotis.de
author_github: @PheysX
---
# Storefront
* Changed the Twig templates `src/Storefront/Resources/views/storefront/component/checkout/offcanvas-item.html.twig`, `src/Storefront/Resources/views/storefront/page/account/order-history/order-detail-list-item.html.twig`, `src/Storefront/Resources/views/storefront/page/account/order-history/order-item.html.twig`, `src/Storefront/Resources/views/storefront/page/checkout/checkout-aside-item.html.twig` and `src/Storefront/Resources/views/storefront/page/checkout/checkout-item.html.twig` to use the constants defined in `src/Core/Checkout/Cart/LineItem/LineItem.php`.
