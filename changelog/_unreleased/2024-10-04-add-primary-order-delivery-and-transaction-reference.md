---
title: Add primary order delivery and primary order transaction reference
issue: https://github.com/shopware/shopware/issues/4936
author: Hannes Wernery
author_email: hannes.wernery@pickware.de
author_github: @hanneswernery
---
# Core
* Add reference `primaryOrderDelivery` to `Core/Checkout/Order/OrderDefinition.php` to reference the primary order
delivery that is shown in the Administration for direct access and management of the delivery (e.g. changing the state).
* A similar reference `primaryOrderTransaction` is added to `Core/Checkout/Order/OrderDefinition.php` for the same
reasons.
