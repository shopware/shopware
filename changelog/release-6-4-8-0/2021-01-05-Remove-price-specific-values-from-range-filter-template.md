---
title: Remove price-specific values from range-filter template
issue: NEXT-19537
author: Sven Lauer
author_email: sven@sven-lauer.net
author_github: svlauer
---
# Storefront
* Deprecated variable `currencySymbol`, use `unit` instead in
  * `src/Storefront/Resources/app/storefront/src/plugin/listing/filter-range.plugin.js`
  * `src/Storefront/Resources/views/storefront/component/listing/filter/filter-range.html.twig`
* Changed `src/Storefront/Resources/views/storefront/component/listing/filter/filter-range.html.twig`
  * Deprecated CSS class `filter-range-currency-symbol`, use `filter-range-unit` instead
  * Changed snippets `listing.filterRangeActiveMinLabel`, `listing.filterRangeActiveMaxLabel` and `listing.filterRangeErrorMessage`
  * Added variables `minKey` and `maxKey` for dynamic input labels
  * Added variables `minInputValue` and `maxInputValue` for dynamic input values
  * Deprecated variable `price`, use `maxInputValue` instead
  * Deprecated twig block `component_filter_range_min_currency_symbol`, use `component_filter_range_min_unit` instead
* Changed `src/Storefront/Resources/views/storefront/component/listing/filter-panel.html.twig` to use the new variables
* Deprecated forwarding of `price` property in `src/Storefront/Resources/views/storefront/component/listing/filter-panel.html.twig`
