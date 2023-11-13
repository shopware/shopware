---
title: Implement flow builder templates
issue: NEXT-22616
---
# Administration
* Changed `src/module/sw-flow/index.js` to update routes:
  * `/flow/index` will redirect to `/flow/index/flows`. 
  * `/flow/index/flows` for showing component `sw-flow-index` on **My Flows** tab.
  * `/flow/index/templates` for showing component `sw-flow-list-flow-templates` **Flow Templates** tab.
* Added component `src/module/sw-flow/page/sw-flow-index` to add the tabs show `My flows` tab and `Flow Templates` tab.
* Added component `src/module/sw-flow/view/listing/sw-flow-list-flow-templates` for showing My Templates tab content.
* Changed component `src/module/sw-flow/page/listing/sw-flow-list` to new location `src/module/sw-flow/view/listing/sw-flow-list/index.js`. Deprecated some blocks because they have been moved to module `src/module/sw-flow/page/sw-flow-index`
  * The following components got deprecated:
    * `sw_flow_list_search_bar`
    * `sw_flow_list_smart_bar_header`
    * `sw_flow_list_smart_bar_actions`
* Added method `onDuplicateFlow` to `src/module/sw-flow/page/listing/sw-flow-list/index.js` to duplicate the flow feature.
* Added block `sw_flow_list_grid_actions_duplicate` to `src/module/sw-flow/page/listing/sw-flow-list/sw-flow-list.html.twig` to show the duplicate menu item on grid table.
