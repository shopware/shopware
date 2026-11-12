---
title: List all custom fields as text editor variables
issue: 21961
---

# Administration

* Changed `cmsService` to load all mappable custom fields in pages of 100, sorted by name, so the "Insert variable" dropdown of the CMS text editor lists more than 50 custom fields.
* Added the button config option `searchable` to toolbar buttons with `children` in `sw-text-editor`, which shows a search field above the children. The data mapping button uses it.
* Added the blocks `sw_text_editor_toolbar_button_child_search` and `sw_text_editor_toolbar_button_child_search_empty` to `sw-text-editor-toolbar-button`.
* Changed `sw-text-editor` to update the data mapping children when the custom fields finish loading after the editor was created.
