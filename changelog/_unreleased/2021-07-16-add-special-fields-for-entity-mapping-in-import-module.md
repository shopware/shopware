---
title: add-special-fields-for-entity-mapping-in-import-module
issue: next-16043
flag: FEATURE_NEXT_8097
author: Malte Janz
author_email: m.janz@shopware.com 
author_github: Malte Janz
---
# Core
* Added the functionality to set user specified default values during an import if the csv field is not set or empty
* Added the functionality to check for user specified required csv fields and log the errors
* Added a new event `Shopware\Core\Content\ImportExport\Event\ImportExportBeforeImportRowEvent` which is fired for each raw CSV row during an import and can modify the row
* Added a new `Shopware\Core\Content\ImportExport\Exception\RequiredByUserException`
* Added `isRequiredByUser()`, `isUseDefaultValue()` and `getDefaultValue()` methods to `Shopware\Core\Content\ImportExport\Processing\Mapping\Mapping`
* Deprecated the `getMappedDefault()` and `getDefault()` methods inside `Shopware\Core\Content\ImportExport\Processing\Mapping\Mapping`.
  If you want to use the user specified default value use the new method `getDefaultValue()` instead.

___
# Administration
* Added `systemRequiredFields` object and `loadSystemRequiredFieldsForEntity(entity)` method to the `sw-import-export-edit-profile-modal` component
  Which changes based on the selected source entity.
* Changed the mapping tab location and block `sw_import_export_edit_profile_modal_tabs_field_mappings` in the `sw-import-export-edit-profile-modal` twig template
* Deprecated the old mapping tab and block `sw_import_export_edit_profile_modal_tabs_mapping ` in the `sw-import-export-edit-profile-modal` twig template
* Added a new template for the mapping tab in the `sw-import-export-edit-profile-modal` twig template
* Added an optional property `systemRequiredFields` to the `sw-import-export-edit-profile-modal-mapping` component disable the required switch for system required fields
* Added two new columns to mark the field as required by the user and allow specifying a default value to the the `sw-import-export-edit-profile-modal-mapping` component
* Changed the template to show the newly added columns and an empty screen in the `sw-import-export-edit-profile-modal-mapping` component
* Added a new method `getSystemRequiredFields(entityName, depth)` to the `importExportMapping.service.js` to get all required fields for a given entity
