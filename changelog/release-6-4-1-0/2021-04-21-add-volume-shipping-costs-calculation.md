---
title: Add volume shipping costs calculation
issue: NEXT-14873
author_github: @Dominik28111
---
# Core
* Added method `Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection::getVolume()` to get the volume of the line items.
* Added const `Shopware\Core\Checkout\Cart\Delivery\DeliveryCalculator::CALCULATION_BY_VOLUME`.
* Changed method `Shopware\Core\Checkout\Cart\Delivery\DeliveryCalculator::matches()` to be able to match by volume.
