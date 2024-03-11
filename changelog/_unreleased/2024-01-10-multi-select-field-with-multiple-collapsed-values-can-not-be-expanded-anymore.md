---
title: Multi select field with multiple collapsed values can not be expanded anymore
issue: NEXT-33030
---
# Administration
* Removed `tagLimit` data property, `visibleTags`, `numberOfHiddenTags` computed property, `removeTagLimit` method in `sw-select-selection-list`
* Added `valueLimit` prop in `sw-multi-tag-select` component to binding the tag limit
* Added `limit` data property in `sw-multi-tag-select` component to store the tag limit
* Added `visibleValues` computed property in `sw-multi-tag-select` component to get the visible tags
* Added `totalValuesCount` computed property in `sw-multi-tag-select` component to get the total tags count
* Added `invisibleValueCount` computed property in `sw-multi-tag-select` component to get the invisible tags count
* Added `expandValueLimit` method in `sw-multi-tag-select` component to expand the tag limit
* Changed `removeLastItem` method in `sw-multi-tag-select` component to check the tag before removing
