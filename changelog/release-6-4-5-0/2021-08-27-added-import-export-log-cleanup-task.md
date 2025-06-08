---
title: Added import export log cleanup task
issue: NEXT-11765
author: d.neustadt
author_email: d.neustadt@shopware.com 
author_github: dneustadt
---
# Core
* Added `CleanupImportExportLogTask` and `CleanupImportExportLogTaskHandler`
* Changed `FileDeletedSubscriber` to fix files not being deleted in filesystem on deletion of `ImportExportFileEntity` entities
* Added `CascadeDelete` flag to `log` property of `ImportExportFileDefinition` and updated the according foreign key of table `import_export_log` to delete corrensponding logs when expired `import_export_file` entries are deleted
