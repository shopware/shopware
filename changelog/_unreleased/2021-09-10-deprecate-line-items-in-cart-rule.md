---
title: Deprecate line items in cart rule
issue: NEXT-17052
author: Malte Janz
author_email: m.janz@shopware.com 
author_github: Malte Janz
---
# Core
* Deprecated `Shopware\Core\Checkout\Cart\Rule\LineItemsInCartRule` - use `Shopware\Core\Checkout\Cart\Rule\LineItemRule` instead.
* Added `Shopware\Core\Migration\V6_4\Migration1631703921MigrateLineItemsInCartRule` migration to migrate all existing rules of type 'cartLineItemsInCart'
___
# Administration
* Deprecated `sw-condition-line-items-in-cart` component - use `sw-condition-line-item` instead.
