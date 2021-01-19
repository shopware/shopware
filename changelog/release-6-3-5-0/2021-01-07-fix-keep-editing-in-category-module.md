---
title: Fix keep editing in category module
issue: NEXT-13005
author: Tobias Berge
author_email: t.berge@shopware.com 
author_github: @tobiasberge
---
# Administration
* Deprecated data prop `discardChanges` in `sw-category/page/sw-category-detail/index.js`. This is a typo, and the data prop is unused. Please use `forceDiscardChanges` instead.
* Changed the anchor elements inside `sw_tree_items_item_content_default` and `sw_tree_item_children_items_slot_content_default` and added the `.prevent` modifier to the `@click` listener in order to suppress the browser redirect.
