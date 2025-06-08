---
title: Force to select a custom field when custom sorting by custom field
issue: NEXT-13546
---
# Administration
* Changed the condition to render criteria card grid's field selection in `src/Administration/Resources/app/administration/src/module/sw-settings-listing/component/sw-settings-listing-option-criteria-grid/sw-settings-listing-option-criteria-grid.html.twig` to allow user choosing a custom field after adding a sorting criteria.
* Changed `isSaveButtonDisabled` computed property in `src/Administration/Resources/app/administration/src/module/sw-settings-listing/page/sw-settings-listing-option-base/index.js` to disable save button if user add new custom field without selecting a specific custom field.
* Changed `createdComponent` method in `src/Administration/Resources/app/administration/src/module/sw-settings-listing/page/sw-settings-listing-option-create/index.js` to `fetchCustomFields` before creating new productSortingEntity.
* Changed `onSave` method in `src/Administration/Resources/app/administration/src/module/sw-settings-listing/page/sw-settings-listing-option-create/index.js` to `transformCustomFieldCriterias` before creating and filter `customField` field.
