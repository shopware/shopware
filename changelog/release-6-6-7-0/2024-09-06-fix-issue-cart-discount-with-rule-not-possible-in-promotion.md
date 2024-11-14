---
title: Fix issue cart discount with rule not possible in promotion
issue: NEXT-38112
---
# Core
* Changed `match` method in the following files to allow `LineItemScope` to be passed as an argument:
    - `src/Core/Checkout/Cart/Rule/GoodsPriceRule.php`
    - `src/Core/Checkout/Cart/Rule/GoodsCountRule.php`
