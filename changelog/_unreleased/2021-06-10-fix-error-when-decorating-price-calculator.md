---
title: Fix error when decorating price calculator
issue: <tbd>
author: René Hrdina
author_email: rene.hrdina@styleflasher.at
author_github: @darinda
---
# Core
* Changed the type of the `$priceCalculator` argument in the `ProductCartProcessor` class to 
  `AbstractProductPriceCalculator` to allow decorating the price calculator as described
   [in the official documentation](https://developer.shopware.com/docs/guides/plugins/plugins/checkout/cart/customize-price-calculation#decorating-the-calculator)
