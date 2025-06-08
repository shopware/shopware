---
title: Fix rule condition price listprice percentage ratio to actually use ratios
issue: NEXT-36837
author: p.dinkhoff
author_email: p.dinkhoff@shopware.com
author_github: p.dinkhoff
---
# Core
* Changed the behaviour of `Core/Checkout/Cart/Rule/LineItemListPriceRatioRule.php` to use actual ratios (e.g. `0.25`) instead of percentage differences (e.g. `75`). This was the intended behaviour from the start and how it is documented
