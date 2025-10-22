---
title: Rearrange CartSavedEvent in RedisCartPersister
issue: https://github.com/shopware/shopware/issues/6872
author_github: @En0Ma1259
---
# Core
* Changed dispatch event order in `Shopware\Core\Checkout\Cart\RedisCartPersister`. `Shopware\Core\Checkout\Cart\Event\CartSavedEvent` will be dispatched after setting the cart. Align dispatch order with `Shopware\Core\Checkout\Cart\CartPersister`
