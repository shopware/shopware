---
title: Fix discount calculation in order detail page
issue: NEXT-32770
author: Lukas Rump
---
# Core
* Changed `OrderConverter::convertToOrder` to skip delivery validation if conversionContext shouldn't include deliveries
___
# Administration
* Changed `sw-order-detail` to toggle automatic promotions on order update
