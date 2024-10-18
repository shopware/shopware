---
title: Fix flow for setting delivery status does not reliably set status for orders with shipping discounts
issue: NEXT-00000
author: Marina Egner
author_github: @magraina
---
# Core
* Changed `Shopware\Core\Content\Flow\Dispatching\ActionSetOrderStateAction` to evaluate the primary order delivery via sorting and filtering
