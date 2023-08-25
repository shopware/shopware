---
title: Fix shipping cost editing by admin in not default currency
issue: NEXT-29081
---
# Core
* Changed method `Shopware\Core\Checkout\Cart\Delivery\DeliveryProcessor::process` for support stored manual edit shipping cost in cart extensions `MANUAL_SHIPPING_COSTS`, to avoid duplicated calculator cart shipping cost.
* Changed method `Shopware\Core\Checkout\Cart\Delivery\DeliveryCalculator::calculateDelivery` for support skip calculator currency factor when manual edit shipping cost in cart extensions `MANUAL_SHIPPING_COSTS`.
