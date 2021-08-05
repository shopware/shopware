---
title: Fix that the checkboxes in the category tree don´t get hidden after closing
issue: NEXT-11418
author: Jannis Leifeld
author_email: j.leifeld@shopware.com 
author_github: @jleifeld
---
# Administration
* Added computed properties `selectedItemsPathIds` and `checkedItemIds` to the `sw-tree` component
* Added bound properties `selectedItemsPathIds` and `checkedItemIds` to the `items` slot in the `sw-tree` component
* Changed the data value `checked` to a computed value with getter and setter
* Added `selectedItemsPathIds` and `checkedItemIds` to the `sw-tree-item` in the category tree
