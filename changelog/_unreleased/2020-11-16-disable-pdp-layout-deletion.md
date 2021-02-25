---
title: Disable the deletion of product detail page layout
issue: NEXT-12062
---
# Administration
* Changed `isActive()` method in `sw-cms-list-item` component to show an active state if needed.
* Changed `deleteDisabledToolTip()` method in `sw-cms-list` component to show a correct tooltip content.
* Changed `getColumnConfig()` method in `sw-cms-list` component to replace "Assigned categories" by "Assignments" label.
* Changed `sw_cms_list_listing_list_data_grid_actions_edit_delete` and `sw_cms_list_listing_list_item_option_delete` blocks in `sw-cms-list` component template to disable the delete menu item if needed.
* Added `sw_cms_list_listing_list_data_grid_column_assignments` block in `sw-cms-list` component template to display assignments quantity. 
