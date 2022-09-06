---
title: fix-setting-shipping-cost-zero-in-admin
issue: NEXT-19510
author: Felix von WIRDUZEN
author_email: felix@wirduzen.de
author_github: @wirduzen-felix
---
# Core
* Fixed a problem where the shipping cost was set to 0 but the shipping cost tax was not. Reverted changes from 
commit `89b3ee8` in `DeliveryProcessor.php` and added additional condition in `DeliveryCalculator.php`
