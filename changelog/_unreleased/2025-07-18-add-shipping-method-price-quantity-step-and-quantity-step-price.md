---
title: Add ShippingMethodPrice quantityStep & quantityStepPrice fields
author: Benjamin Wittwer
author_email: Discord.Benjamin@web.de
author_github: gecolay
---
# Core
* Added `quantity_step` & `quantity_step_price` columns to `shipping_method_price` table
* Added `quantityStep` & `quantityStepPrice` fields to `Checkout/Shipping/Aggregate/ShippingMethodPrice/ShippingMethodPriceDefinition.php`
* Added `quantityStep` & `quantityStepPrice` fields and methods to `Checkout/Shipping/Aggregate/ShippingMethodPrice/ShippingMethodPriceEntity.php`
* Changed `Checkout/Cart/Delivery/DeliveryCalculator.php` to correctly calculate quantity step prices
