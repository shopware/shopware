---
title: Only consider filterable discount packages
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---

# Core
* Changed the `Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\SetGroupScopeFilter` to only filter for price definitions when they implement the `Shopware\Core\Checkout\Cart\Price\Struct\FilterableInterface` instead of explicitly checking for the `getFilter` method

___
# Upgrade Information
## Add the correct interface to filterable price definitions
If a price definition should be filterable, explicitly implement the `Shopware\Core\Checkout\Cart\Price\Struct\FilterableInterface`, which defines the required `getFilter()` method.
___
# Next Major Version Changes
## Filterable price definitions now require an explicit interface
Previously, a price definition was treated as filterable when it implemented a `getFilter()` method. From now on, price definitions must explicitly implement the
`Shopware\Core\Checkout\Cart\Price\Struct\FilterableInterface`, which defines the required `getFilter()` method.
