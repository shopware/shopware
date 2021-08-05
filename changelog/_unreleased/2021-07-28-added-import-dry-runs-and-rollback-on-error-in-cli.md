---
title: Added import dry runs and rollback on error in CLI
issue: NEXT-16187
flag: FEATURE_NEXT_8097
author: d.neustadt
author_email: d.neustadt@shopware.com 
author_github: dneustadt
---
# Core
* Added option for `import:entity` CLI command to print potential errors after import
* Added option for `import:entity` CLI command to do a complete rollback of all previous transitions if import contained errors
* Changed `ImportExportService` to be able to set up a dry run import, doing a rollback of all transitions regardless of errors  
* Added option for `import:entity` CLI command to do a dry run import
* Added `WriteCommandExceptionEvent` dispatched in `EntityWriteGateway` when execution of write commands throws exception
* Added `erroneous` boolean property to `WriteCommand` to markup the specific write command having thrown an exception
* Added `result` field to `ImportExportLogDefinition` containing the results of the import in form of numeric representations of sucessfull, erroneous or skipped insert/update statements for each entity
___
# Administration
* Added button to start import dry run
* Added label in import activity log signifying if log belong to a dry run
* Added context menu entry and modal to display results table of a previous import from activity log
