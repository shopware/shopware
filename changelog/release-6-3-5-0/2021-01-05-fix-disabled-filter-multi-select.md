---
title: Fix filter without results feature does not work with sidebar filter
issue: NEXT-12233
---
# Storefront
* Changed `disableFilter` in `Resources/app/storefront/src/plugin/listing/filter-multi-select.plugin.js` to set attribute `disabled`.
* Changed `enableFilter` in `Resources/app/storefront/src/plugin/listing/filter-multi-select.plugin.js` to remove attribute `disabled`.
