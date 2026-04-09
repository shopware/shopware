---
title: Fix recursive cart lock usage
author: Max Stegmeyer
author_email: m.stegmeyer@shopware.com
---
# Core
* Changed `Shopware\Core\Checkout\Cart\CartLocker` to not acquire a lock on recursive calls. This allows e.g. triggered events to use the cart lock without causing a deadlock.
