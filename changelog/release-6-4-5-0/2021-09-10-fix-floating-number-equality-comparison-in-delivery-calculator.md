---
title: Fix floating number equality comparison in DeliveryCalculator
issue: NEXT-17207
author: David Lochner
author_email: lochner@nexxo.de
author_github: nexxome
---
# Core
* Changed method `Shopware\Core\Checkout\Cart\Delivery\DeliveryCalculator::matches()` to ensure that the equality of floating numbers is checked correctly.
