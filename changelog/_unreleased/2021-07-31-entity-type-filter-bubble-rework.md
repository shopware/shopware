---
title: Entity type filter bubble rework
issue: NEXT-15987
flag: FEATURE_NEXT_6040
---
# Administration
*  Added `showModuleFiltersContainer` data property in `src/Administration/Resources/app/administration/src/app/component/structure/sw-search-bar/index.js` to handle show/hide module filters dropdown
*  Added `getEntityIcon` method in `src/Administration/Resources/app/administration/src/app/component/structure/sw-search-bar/index.js` to handle getting icon of module by entity name
*  Added `sw_search_bar_types_module_filters_container` block in `src/Administration/Resources/app/administration/src/app/component/structure/sw-search-bar/sw-search-bar.html.twig` to display new module filter dropdown
