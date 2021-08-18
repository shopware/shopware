---
title: Add possibility to mark entities as export or import only
issue: NEXT-16621
flag: FEATURE_NEXT_8097
 
---
# Administration
* Added the possibility to mark entities as importable or exportable only in the `sw-import-export-edit-profile-modal` component by setting the `type` property in the `supportedEntities` array
* Added block `sw_import_export_edit_profile_modal_tabs_type_field_inner` to `src/Administration/Resources/app/administration/src/module/sw-import-export/component/sw-import-export-edit-profile-modal/sw-import-export-edit-profile-modal.html.twig`
* Added block `sw_import_export_edit_profile_modal_tabs_object_type_field_inner` to `src/Administration/Resources/app/administration/src/module/sw-import-export/component/sw-import-export-edit-profile-modal/sw-import-export-edit-profile-modal.html.twig`
