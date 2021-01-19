---
title: Fix inheritance for purchase prices
issue: NEXT-11250
author: OliverSkroblin
author_email: o.skroblin@shopware.com 
author_github: OliverSkroblin
---
# Core
* Changed `Migration1593698606AddNetAndGrossPurchasePrices` to fix purchase price calculation for inherited variant values 
___
# Administration
* Changed `administration/src/module/sw-product/page/sw-product-detail/index.js` to fix purchase price inheritance
