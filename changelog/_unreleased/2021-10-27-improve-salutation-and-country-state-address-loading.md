---
title: Improve address salutation and countryState loading
issue: NEXT-17764
author: Max Stegmeyer
author_email: m.stegmeyer@shopware.com 
---
# Core
* Changed `SalesChannelContextFactory` to load `salutation` associations to addresses
* Changed `ListAddressRoute` to load `salutation` and `countryState` associations to addresses
___
# Storefront
* Changed `AccoundEditOrderPageLoader` to load `salutation` and `countryState` associations to order addresses
* Changed `CheckoutFinishPageLoader` to load `salutation` and `countryState` associations to order addresses
