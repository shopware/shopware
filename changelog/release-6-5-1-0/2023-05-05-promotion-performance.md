---
title: Promotion performance
issue: NEXT-21465
author: Oliver Skroblin
author_email: o.skroblin@shopware.com
---

# Core
* Changed `PromotionCalculator`, `DiscountAbsoluteCalculator`, `DiscountFixedPriceCalculator`, `DiscountPercentageCalculator`, `FilterSorterPriceAsc` and `FilterSorterPriceDesc`, to consume less memory and process discount packages faster for cart with high quantity values
