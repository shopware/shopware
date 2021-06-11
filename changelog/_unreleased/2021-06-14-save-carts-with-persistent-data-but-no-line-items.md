---
title: Save carts with persistent data but no line items.
issue: NEXT-15702
author: Andreas Allacher
author_email: andreas.allacher@massiveart.com
author_github: @AndreasA
---
# Core
* Changed method `save` in `Shopware\Core\Checkout\Cart\CartPersister` to dispatch an event before the cart is saved. This event determines, if the cart has to be saved.
* Added new event `Shopware\Core\Checkout\Cart\Event\BeforeCartSavedEvent` to be fired before the cart is saved. This event determines, if a cart has to be saved. Furthermore, it allows modifications to the cart just before it is saved, e.g. remove unnecessary extensions.
* Added event subscribers for the new event to ensure the cart is saved, if it has line items, a customer comment, affiliation code, campaign code or manual shipping charges. 
