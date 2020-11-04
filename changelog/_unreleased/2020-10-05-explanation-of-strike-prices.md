---
title: Explanation of strike prices
issue: NEXT-11532
author: Niklas Limberg
author_github: @NiklasLimberg
---
# Storefront
*  Added empty snippets for `listing.beforeStrikePrice` and `listing.aftereStrikePrice`
*  Changed Class `.strikeprice-text` in `_product-box.scss`
*  Added `listing.beforeStrikePrice` and `listing.aftereStrikePrice` to `price-unit.html.twig` and `buy-widget-price.html.twig`
*  Changed `price-unit.html.twig` and `buy-widget-price.html.twig` to only display `text-decoration: line-through;` when `listing.beforeStrikePrice` and `listing.aftereStrikePrice` are empty