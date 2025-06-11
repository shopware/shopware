---
title: E-invoice vertical tax calculation
issue: #9310
---
# Core
* Added new Order field `taxCalculationType`. Tax calculation type will be set in `\Shopware\Core\Checkout\Cart\Order\OrderConverter::convertToOrder` from current context value.
* Added vertical tax calculation for gross orders in `\Shopware\Core\Checkout\Document\Zugferd\ZugferdDocument`. `\Shopware\Core\Checkout\Document\Zugferd\ZugferdDocument::getPrice` has a new optional argument `type` to add the price into the right area for vertical calculation 
