---
title: Remove language switch from import/export
issue: 11568
author: Lars Kemper
author_email: l.kemper@shopware.com
author_github: @larskemper
---
## Core
- Deprecated the `$label` property and the `getLabel()`, `setLabel()`, `getTranslations()`, `setTranslations()` methods in `Shopware\Core\Content\ImportExport\ImportExportProfileEntity`.
- Deprecated:
    - `Shopware\Core\Content\ImportExport\ImportExportProfileTranslationCollection`
    - `Shopware\Core\Content\ImportExport\ImportExportProfileTranslationDefinition`
    - `Shopware\Core\Content\ImportExport\ImportExportProfileTranslationEntity`
- Changed `createLog()` and `getConfig()` in `Shopware\Core\Content\ImportExport\Service\ImportExportService` to use `$technicalName` instead of `$label` as filename.
- Changed `generateFilename()` in `Shopware\Core\Content\ImportExport\Service\FileService` to use `$technicalName` instead of `$label` as profile name.
___
## Administration
- Deprecated `sw_import_export_edit_profile_general_container_name` block in `src/module/sw-import-export/component/sw-import-export-edit-profile-general/sw-import-export-edit-profile-general.html.twig`
- Deprecated `sw_import_export_view_profile_profiles_listing_column_label` block in `src/module/sw-import-export/view/sw-import-export-view-profiles/sw-import-export-view-profiles.html.twig`
- Deprecated `sw_import_export_language_switch` block in `src/module/sw-import-export/page/sw-import-export/sw-import-export.html.twig`
