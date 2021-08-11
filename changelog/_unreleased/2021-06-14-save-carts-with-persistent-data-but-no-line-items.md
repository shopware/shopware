---
title: Save carts with persistent data but no line items.
issue: NEXT-15857
author: Andreas Allacher
author_email: andreas.allacher@massiveart.com
author_github: @AndreasA
---
# Core
* Changed method `save` in `Shopware\Core\Checkout\Cart\CartPersister` to dispatch an event before the cart is saved to determinate, if the cart has to be saved.
* Added new event `Shopware\Core\Checkout\Cart\Event\CartVerifyPersistEvent` to be fired before the cart is saved. This event determines, if a cart has to be saved. Furthermore, it allows modifications to the cart just before it is saved, e.g. remove unnecessary extensions.
