---
title: Set shipping costs to 0 for admin order
issue: NEXT-16666
author: Niklas Limberg
author_email: n.limberg@shopware.com
author: NiklasLimberg
author_github: NiklasLimberg
---
# Core
* Changed `Shopware\Core\Checkout\Cart\Delivery\DeliveryCalculator::calculateDelivery()` to apply manual shipping cost regardless of unit price