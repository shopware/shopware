---
title: Open categories in new tabs with right-click
issue: NEXT-9586
---
# Administration
* Added a method `getCategoryUrl` in `src/module/sw-category/component/sw-category-tree/index.js`.
* Added a new prop `getItemUrl` in `src/app/component/tree/sw-tree-item/index.js`.
* Added a method `showItemUrl` in `src/app/component/tree/sw-tree-item/index.js`.
* Added `href` attribute into <a> tag in `{% block sw_tree_items_item_content_default %}` and `{% block sw_tree_item_children_items_slot_content_default %}` in `src/app/component/tree/sw-tree-item/sw-tree-item.html.twig`
