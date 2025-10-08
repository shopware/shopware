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
When you want to have a price definition which supports filtering when building discount packages, it was previously considered when the `getFilter()` method is implemented, for Shopware 6.8.0.0 the price definition needs to implement the `Shopware\Core\Checkout\Cart\Price\Struct\FilterableInterface`
