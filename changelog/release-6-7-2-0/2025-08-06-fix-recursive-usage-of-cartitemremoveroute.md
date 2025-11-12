---
title: Fix advanced prices for fixed item price discount
author: Max Stegmeyer
author_github: @mstegmeyer
---
# Core
* Changed `Shopware\Core\Checkout\Promotion\Subscriber\Storefront\StorefrontCartSubscriber` not use the CartItemRemoveRoute as this creates recursion but to just remove from the LineItemCollection.
