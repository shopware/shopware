---
title: Refactor delivery time units
issue: NEXT-21958
author: Silvio Kennecke
author_github: @silviokennecke
---
# Administration
* Changed `sw-settings-delivery-time-detail` component to support unit `hour`
___
# Core
* Changed `\Shopware\Core\System\DeliveryTime\DeliveryTimeEntity` to add missing unit constants
* Changed `\Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate::createFromDeliveryTime` to use constants instead of hardcoded values
