---
title: Payload protection
issue: NEXT-26112
author: Oliver Skroblin
author_email: o.skroblin@shopware.com
---
# Core
* Added `\Shopware\Core\Checkout\Cart\LineItem\LineItem::$payloadProtection`, which allows to protect the payload of a line item from being exposed via store api 
___
# API
* Removed `lineItem.payload.purchasePrices` from store api cart responses.
