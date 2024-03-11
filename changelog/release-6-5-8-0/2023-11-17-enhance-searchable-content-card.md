---
title: Enhance searchable content card
issue: NEXT-31662
---
# Administration
* Changed `searchConfigs` watching property in `sw-settings-search-searchable-content-customfields` component to get rid of the `undefined` error if methods are invoked when `customGrid` ref isn't available
* Changed `productSearchFieldCriteria` computed property in `sw-settings-search-searchable-content` component to make `searchConfigId` nullable
