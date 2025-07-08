---
title: Fix the issue where a criteria limit of 500 prevents loading admin modules
---
# Administration
* Changed methods `loadGroups` and `loadConfigSettingGroups` in `sw-product-detail-variants` component to load all groups instead of only 500
