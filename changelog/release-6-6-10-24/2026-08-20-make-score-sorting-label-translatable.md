---
title: Make score sorting label translatable
issue: 13431
---
# Storefront
* Changed `Resources/views/storefront/component/sorting.html.twig` to render the `filter.sortByScore` snippet as the label of the `score` product sorting instead of its database label. The `score` sorting is locked, so its label could not be edited in the administration and only existed for `en-GB` and `de-DE`; it can now be translated for any language through snippet management or a theme snippet file. All other sortings keep rendering the label configured in the administration.
