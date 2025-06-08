---
title: Storefront pagination and filter are not working when pressing back browser
issue: NEXT-13189
---
# Storefront
* Changed method `setValuesFromUrl` in `src/Storefront/Resources/app/storefront/src/plugin/listing/filter-multi-select.plugin.js` to remove the checked filter when going back from the browser.
* Added method `setValuesFromUrl` in `src/Storefront/Resources/app/storefront/src/plugin/listing/listing-pagination.plugin.js` to update the page param when going back from the browser.
* Added a new parameter `pushHistory` (default true) of method `changeListing` in `src/Storefront/Resources/app/storefront/src/plugin/listing/listing.plugin.js` to optionally do not push state into the browser's history when going back from the browser.
* Added a new parameter `overrideParams` of method `changeListing` in `src/Storefront/Resources/app/storefront/src/plugin/listing/listing.plugin.js` to optionally override the request parameters from filter plugins.
* Added `window.onpopstate` event listener when initializing `ListingPlugin` to refresh the listing plugin and filter plugins when going back from the browser.
