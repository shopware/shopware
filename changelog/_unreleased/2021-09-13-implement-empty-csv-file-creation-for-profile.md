---
title: Implement empty csv file creation for profile
issue: NEXT-16084
flag: FEATURE_NEXT_15998
author: Malte Janz
author_email: m.janz@shopware.com 
author_github: Malte Janz
---
# Core
* Added `createTemplate` method to `Shopware\Core\Content\ImportExport\Service\ImportExportService` which creates a template CSV file for the given profile.
* Added `ACTIVITY_TEMPLATE` constant to `Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity`.
___
# API
* Added endpoint `/api/_action/import-export/prepare-template-file-download` in `Shopware\Core\Content\ImportExport\Controller\ImportExportActionController`.
___
# Administration
* Added `download template` context menu action in profile listing to `sw-import-export-view-profiles` component.
* Added snippet `sw-import-export.profile.downloadTemplateLabel`.
