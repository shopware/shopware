---
title: Product detail page error modal should have a "do not show again" option
issue: NEXT-21806
---
# Administration
* Added property `cmsMissingElementDontRemind` in `src/module/sw-cms/page/sw-cms-detail/index.js`
* Added `onChangeDontRemindCheckbox` method in `sw-cms-detail` component
* Changed `sw_cms_missing_element_modal` to add don't ask me again checkbox option.
* Changed `pageIsValid` method in `sw-cms-detail` component to load setting from the local storage and check the condition to show the CMS missing element modal.
* Changed `onSaveMissingElementModal` method in `sw-cms-detail` component to save setting to local storage.
