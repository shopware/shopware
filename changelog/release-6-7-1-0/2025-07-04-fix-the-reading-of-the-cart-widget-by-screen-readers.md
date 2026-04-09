---
title: Fix the reading of the cart widget by screen readers
issue: #9229
author: Michel Bade
author_email: m.bade@shopware.com
author_github: @cyl3x
---
# Storefront
* Changed the `header-cart` in `views/storefront/layout/header/header.html.twig` to be aria labelled by the `cart-widget.html.twig`.
* Changed `views/storefront/layout/header/actions/cart-widget.html.twig` to contain an aria label including cart details.
