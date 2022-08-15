---
title: Edit shipping cost to "0" in an order
issue: NEXT-19510
---
# Core
* Changed `Shopware\Core\Checkout\Cart\Delivery\DeliveryProcessor::process` to skip `calculate` when `total price` in `shipping costs` have value is 0. 
