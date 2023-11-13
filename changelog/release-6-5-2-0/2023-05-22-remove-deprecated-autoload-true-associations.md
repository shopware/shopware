---
title: Remove deprecated autoload === true associations
issue: NEXT-25332
---
# Core
* Changed `Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogDefinition `to remove deprecated autoload === true
* Changed `Shopware\Core\Content\ImportExport\ImportExportFactory::findLog` to load log with the associations `file` and `invalidRecordsLog.file`.
* Changed `Shopware\Core\Content\ImportExport\Service\ImportExportService::getProgress` to load log with the association `file`.
* Changed `Shopware\Core\Content\ImportExport\Service\ImportExportService::findLog` to load log with the associations `file` and `invalidRecordsLog.file`.
___
# Administration
* Changed computed `activityCriteria` in `sw-import-export-activity` component to add association `invalidRecordsLog.file`.
* Changed method `updateActivitiesInProgress` in `sw-import-export-activity` component to add association `file`
___
# Upgrade Information
If you are relying on the association `import_export_log.file`, please associate the definition directly with the criteria because we will remove autoload from version 6.6.0.0.
