---
title: Fix error when decorating price calculator
issue: NEXT-15734
author: René Hrdina
author_email: rene.hrdina@styleflasher.at
author_github: @darinda
---
# Core
* Changed the type of the `$priceCalculator` dependency in the `ProductCartProcessor` class to `AbstractProductPriceCalculator` to allow decorating the price calculator
