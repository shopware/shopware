---
title: Improve search widget accessibility
issue: #7877
---
# Storefront
* Deprecated `storefront/src/helper/arrow-navigation.helper.js` for v6.8.0 because it is obsolete.
* Changed `storefront/src/plugin/header/search-widget.plugin.js` to add proper keyboard navigation via tab and arrow keys, using real focus states.
* Added `aria-describedby` attribute to the search input field in `layout/header/search.html.twig` to read out search result changes for screen reader users.