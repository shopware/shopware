---
title: Adds MIN() Function to cheapestPrice Accessor
issue: #4053
author: Ullrich Löblein
author_email: lul@shd.de
author_github: @ulloe
---
# Core
* Changed method `addSortings` in `src/Core/Framework/DataAbstractionLayer/Dbal/CriteriaQueryBuilder.php` to select product variants according to min price.
___
# Upgrade Information
## ProductListing with Variants and sort by price
Grouping with `GROUP BY product.display_group`, which is necessary to process product variants, only works without SQL Mode `only_full_group_by`. When this mode is disabled, it causes rows to be dropped - potentially the one with the cheapest price. This leads to inconsistent sorting of products with variants that differ in price.
Due to sorting by the lowest price, the `cheapestPrice` accessor was removed from the min/max logic in the `CriteriaQueryBuilder`. To ensure that a product is listed based on its cheapest variant, the accessor was supplemented with the `MIN()` function.
