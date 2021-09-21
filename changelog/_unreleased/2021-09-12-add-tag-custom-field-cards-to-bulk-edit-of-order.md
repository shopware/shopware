---
title: Add Tag & Custom field cards to bulk edit of Order
issue: NEXT-17081
---
# Administration
* Added `tagsFormFields` computed property in `app/administration/src/module/sw-bulk-edit/page/sw-bulk-edit-order/index.js` to load tags fields.
* Added `loadCustomFieldSets` method in `app/administration/src/module/sw-bulk-edit/page/sw-bulk-edit-order/index.js` to load custom field sets.
* Added block `sw_bulk_edit_order_tags_card` in `app/administration/src/module/sw-bulk-edit/page/sw-bulk-edit-order/sw-bulk-edit-order.html.twig` to show bulk edit order tags card.
* Added block `sw_bulk_edit_order_custom_field_card` in `app/administration/src/module/sw-bulk-edit/page/sw-bulk-edit-order/sw-bulk-edit-order.html.twig` to show bulk edit order custom field card
