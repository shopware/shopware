---
title: Added lower bound to Range-Filter
issue: NEXT-18354
author: Simon Vorgers
author_email: s.vorgers@shopware.com
author_github: SimonVorgers
---
# Storefront
* Added variable `lowerBound` in `src/Storefront/Resources/views/storefront/component/listing/filter-panel.html.twig`
* Changed variable `filterRangeOptions` in `src/Storefront/Resources/views/storefront/component/listing/filter/filter-range.html.twig`
* Changed `_onChangeInput` in `src/Storefront/Resources/app/storefront/src/plugin/listing/filter-range.plugin.js` to validate via lowerBound
* Added snippet `listing.filterRangeLowerBoundErrorMessage`