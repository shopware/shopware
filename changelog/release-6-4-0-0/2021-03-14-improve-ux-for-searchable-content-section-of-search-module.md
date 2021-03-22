---
title: Improve UX for searchable content section of Search module
issue: NEXT-14244
---
# Administration
*  Changed the function `getProductSearchFieldColumns()` in `src/module/sw-settings-search/component/sw-settings-search-searchable-content/index.js` to enable the sorting function for searchable content grid data.
* Changed the component `sw-settings-search-searchable-content-general` to remove function add/ remove searchable content item.
* Changed the component `sw-settings-search-searchable-content-customfields` to remove function reset to default.
* Changed `sw-settings-search-searchable-content.html.twig` to add new block `sw_settings_search_searchable_rebuild_link` to link to rebuild search index section.
