---
title: Restore customer address from order for context
issue: NEXT-40613
author: Michel Bade
author_email: m.bade@shopware.com
author_github: @cyl3x
---
# Core
* Changed `CustomerAddressEntity` and `OrderAddressEntity` to include a new runtime field `hash`.
* Added an `Shopware\Core\Checkout\Customer\Subscriber\AddressHashSubscriber` which calculates the hash for all loaded `CustomerAddressEntity` and `OrderAddressEntity`.
* Changed `Shopware\Core\Checkout\Cart\Order\OrderConverter::assembleSalesChannelContext` to find matching customer addresses based on the order addresses.
