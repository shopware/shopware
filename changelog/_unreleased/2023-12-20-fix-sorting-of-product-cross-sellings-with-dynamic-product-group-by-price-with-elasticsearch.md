---
title: Fix sorting of product cross sellings with dynamic product group by price with Elasticsearch
issue: NEXT-30879
---
# Core
* Added new migration `src/Core/Migration/V6_6/Migration1702982372FixProductCrossSellingSortByPrice.php` to change sort by from `price` to `cheapestPrice`
* Changed constant value of `\Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingDefinition::SORT_BY_PRICE` from `price` to `cheapestPrice`
