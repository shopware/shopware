---
title: Fix price calculation in variants
issue: NEXT-10599
author: Jannis Leifeld
author_email: j.leifeld@shopware.com 
author_github: Jannis Leifeld
---
# Administration
* Added checks in `removePriceInheritation` in `sw-product-price-form` to fix the price inheritance removing when `purchasePrices` don´t exists
