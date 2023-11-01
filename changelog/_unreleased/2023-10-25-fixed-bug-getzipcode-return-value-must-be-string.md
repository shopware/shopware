---
title: Fixed bug getZipcode return value must be string
issue: NEXT-31148
author: Florian Keller
author_email: f.keller@shopware.com
---
# Core
* Changed `Shopware\Core\Checkout\Order\Aggregate\OrderAddress::getZipcode()` to avoid nullable return value, string is expected.

