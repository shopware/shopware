---
title: Show missing element modal
issue: NEXT-11886
---
# Administration
* Added `sw-cms-missing-element-modal` in `sw-cms` component.
* Added `sw_cms_detail_missing_element_modal` block in `sw-cms-detail` component template.
* Added methods in `sw-cms-detail` component:
    * `getMissingElements`
    * `onCloseMissingElementModal`
    * `onSaveMissingElementModal`
* Changed `onSave` method in `sw-cms-detail` component to show missing element modal if needed.
