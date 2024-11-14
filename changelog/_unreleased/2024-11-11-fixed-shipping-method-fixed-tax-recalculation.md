---
title: Fixed shipping method fixed tax recalculation
issue: NEXT-39349
author: Michel Bade
author_email: m.bade@shopware.com
author_github: @cyl3x
---
# Core
* Changed `Shopware\Core\Checkout\Cart\Delivery\DeliveryCalculator` to use the tax id directly for calculating fixed taxes of shipping costs
* Changed `Shopware\Core\Checkout\Cart\Order\RecalculationService` to also load the TaxEntity of a shipping method
