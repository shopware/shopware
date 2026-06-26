---
title: Optimize hot-path rule, price and listing collection operations
issue: NEXT-00000
author: Florian Ressel
author_email: florian@genxtreme.de
---
# Core
* Changed `RuleCollection::getIdsByArea()` to deduplicate rule ids with a keyed set instead of `array_unique(array_merge())` inside the loop, removing the quadratic complexity on the cart calculation hot path.
* Changed `SalesChannelContext::getRuleIdsByAreas()` to collect rule ids into a keyed set, removing the quadratic complexity on the cache-key generation hot path.
* Changed `RuleAreaUpdater::update()` to collect rule areas with a keyed set instead of repeated `array_unique(array_merge())` during rule indexing.
* Changed `ProductPriceCalculator::filterRulePrices()` to index the available price rule ids once instead of re-scanning the whole price collection for every active context rule id, reducing the per-product price calculation from `O(ruleIds * prices)` to `O(prices)`.
* Changed `AggregationListingProcessor` to merge filter aggregations a single time and to compute the filtered base collection once outside the loop, removing redundant work on every product listing request.
