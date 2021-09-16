---
title: Implement new profile from CSV wizard
issue: NEXT-16083
flag: FEATURE_NEXT_15998
author: Malte Janz
author_email: m.janz@shopware.com 
author_github: Malte Janz
---
# Core
* Added `Shopware\Core\Content\ImportExport\Service\MappingService` which provides functionality to create a CSV template for a given profile or create a mapping for a given CSV file.
* Added `Shopware\Core\Content\ImportExport\Service\FileService` which provides functionality regarding import / export files (these were mostly private functions inside the `ImportExportService` before).
* Added `FileEmptyException` and `InvalidFileContentException` which may be thrown during mapping creation from existing CSV file in the `MappingService`
* Changed `Shopware\Core\Content\ImportExport\Service\ImportExportService` to use the `FileService` (constructor parameters changed)
* Changed `Shopware\Core\Content\ImportExport\ImportExport` to use the `FileService` (constructor parameters changed)
* Changed `Shopware\Core\Content\ImportExport\ImportExportFactory` to use the `FileService` (constructor parameters changed)
* Changed `Shopware\Core\Content\ImportExport\Controller\ImportExportActionController` to use the `MappingService` (constructor parameters changed)
* Deprecated `updateFile` method inside `Shopware\Core\Content\ImportExport\Service\ImportExportService`. Use `FileService->updateFile(...)` instead.
___
# API
* Added `POST` endpoint at `/api/_action/import-export/mapping-from-template` in `Shopware\Core\Content\ImportExport\Controller\ImportExportActionController`. This endpoint uses a `sourceEntity`, `delimiter`, `enclosure` and CSV-`file` to construct and return a mapping array.
___
# Administration
* Added `sw-import-export-new-profile-wizard` component
* Added `sw-import-export-new-profile-wizard-csv-page` component
* Added `sw-import-export-new-profile-wizard-general-page` component
* Added `sw-import-export-new-profile-wizard-mapping-page` component
* Added `getMappingFromTemplate` method to `importExport.service.js`, which uses the new endpoint.
* Changed `sw-import-export-view-profiles` component to show the `sw-import-export-new-profile-wizard` when creating a new profile instead of the `sw-import-export-edit-profile-modal`.
* Changed `sw-import-export-edit-profile-modal` component to be visible based on new `show` property (defaults to `true`).
