---
title: Fixed bug with 0 price product discount
issue: NEXT-21158
author: Florian Keller
author_email: f.keller@shopware.com
---
# Core

* Changed \Shopware\Core\Checkout\Promotion\Cart\Discount\Calculator\DiscountAbsoluteCalculator to avoid division by zero when product price is 0. 
