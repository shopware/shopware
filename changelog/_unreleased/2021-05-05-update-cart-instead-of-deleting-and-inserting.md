---
title: Update cart instead of deleting and inserting.
issue: NEXT-15169
author: Andreas Allacher
author_email: andreas.allacher@massiveart.com
author_github: @AndreasA
---
# Core
* Changed method `save` in `Shopware\Core\Checkout\Cart\CartPersister` class to insert or update the cart to ensure concurrent requests will not retrieve an empty cart.
