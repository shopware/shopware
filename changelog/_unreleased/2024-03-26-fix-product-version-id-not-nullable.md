---
title: NEXT-00000 - Fix productVersionId to nullable
issue: NEXT-00000
author: Young Lu
author_email: staluxy@gmail.com
author_github: @StoneKnocker
---
# core
* Changed the productVersionId field in `Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity` to nullable
* Removed the requirement for the productVersionId field in `Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition`
