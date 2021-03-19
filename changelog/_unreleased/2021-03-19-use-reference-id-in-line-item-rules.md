---
title: Use reference id in line item rules
issue: NEXT-13475
author: OliverSkroblin
author_email: o.skroblin@shopware.com 
author_github: OliverSkroblin
---
# Core
* Changed `\Shopware\Core\Checkout\Cart\Rule\LineItemWithQuantityRule` to use the `\Shopware\Core\Checkout\Cart\LineItem\LineItem::$referencedId` instead of the `\Shopware\Core\Checkout\Cart\LineItem\LineItem::$id`
