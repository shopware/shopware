---
title: Fix LineItemQuantitySplitter splitting unstackable LineItems
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Core
* Changed behaviour of `\Shopware\Core\Checkout\Cart\LineItem\LineItemQuantitySplitter` to ensure the cloned LineItems are stackable to change their quantity and no `\Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException` is thrown
