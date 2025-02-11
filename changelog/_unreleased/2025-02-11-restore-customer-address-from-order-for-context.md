---
title: Restore customer address from order for context
issue: NEXT-40613
author: Michel Bade
author_email: m.bade@shopware.com
author_github: @cyl3x
---
# Core
* Changed `CustomerAddressEntity` and `OrderAddressEntity` to include a new runtime field `hash`.
* Added an `Shopware\Core\Checkout\Customer\Service\AddressHasher` which calculates a hash for the given `CustomerAddressEntity` or `OrderAddressEntity`.
* Added an `Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\AddressHashStruct` which contains all the information to be included in the hash calculation.
* Added an `Shopware\Core\Checkout\Customer\Event\AddressHashEvent` which is fired by the `AddressHasher` to allow manipulation and extension of the `AddressHashStruct`.
* Changed `Shopware\Core\Checkout\Cart\Order\OrderConverter::assembleSalesChannelContext` to find matching customer addresses based on the order addresses.
