---
title:              Remove price-specific values from range-filter template     # Required
issue:              2241
author:             Sven Lauer
author_email:       sven@sven-lauer.net
author_github:      svlauer
---
# Storefront
* Removed price-specific values from `@Storefront/storefront/component/lists/filter/filter-range.html.twig`:
   * The `name`-attributes of the input fields use the `minKey` and `maxKey` values, instead being hardwired to `min-price` and `maxPrice`.
   * The `currencySymbol` variable is replaced with the `unit` variable .
   (`unit` is set to the value of `currencySymbol` if not defined for backward compatibility)
   * The blocks `component_filter_range_min_currency_symbol` and `component_filter_range_max_currency_symbol` are deprecated in favor of the new blocks `component_filter_range_min_unit` and `component_filter_range_max_unit`.
   * CSS class `filter-range-currency-symbol` renamed to `filter-range-unit`.
   * Introduced new variables `minInputValue` and `maxInputValue` for setting the limits of the input fields (previously hardwired to `0` and `price.max`).
   (Variables default to previously hardwired ones for backward compatibility.)
* Adjusted `@Storefront/storefront/component/lists/filter-panel.html.twig` to new interface (= adding `minInputValue` and `maxInputValue`, removing `price`-value).
* Made range-filter-specific snippets more generic:
  * `listing.filterRangeActiveMinLabel` and `listing.filterRangeActiveMaxLabel` now insert `displayName` instead of being hardwired to `Price`.
  * `listing.listing.filterRangeErrorMessage` now speaks of "minimum value" and "maximum value" instead of "minimum price" and "maximum price".