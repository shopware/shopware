---
title: Mark parts of the Import/Export functionality as internal
issue: NEXT-40446
---
# Core
* Added `\Shopware\Core\Content\ImportExport\Event\ImportExportAfterProcessFinishedEvent` which is dispatched upon completion of import/export operations. This event exposes the `Context`, `ImportExportLogEntity`, and progress information through its respective getter methods.
* Deprecated `\Shopware\Core\Content\ImportExport\Event\ImportExportAfterImportRecordEvent` which will be removed in 6.7.0.0 without replacement
* Deprecated the following classes which will marked as internal in 6.7.0.0
  * `\Shopware\Core\Content\ImportExport\ImportExport`
  * `\Shopware\Core\Content\ImportExport\Processing\Pipe\AbstractPipe`
  * `\Shopware\Core\Content\ImportExport\Processing\Pipe\AbstractPipeFactory`
  * `\Shopware\Core\Content\ImportExport\Processing\Pipe\ChainPipe.php`
  * `\Shopware\Core\Content\ImportExport\Processing\Pipe\EntityPipe.php`
  * `\Shopware\Core\Content\ImportExport\Processing\Pipe\KeyMappingPipe.php`
  * `\Shopware\Core\Content\ImportExport\Processing\Pipe\PipeFactory.php`
___
# Next Major Version Changes
## Changes to the import/export functionality
The following classes are now marked as internal:
* `\Shopware\Core\Content\ImportExport\ImportExport`
* `\Shopware\Core\Content\ImportExport\Processing\Pipe\AbstractPipe`
* `\Shopware\Core\Content\ImportExport\Processing\Pipe\AbstractPipeFactory`
* `\Shopware\Core\Content\ImportExport\Processing\Pipe\ChainPipe`
* `\Shopware\Core\Content\ImportExport\Processing\Pipe\EntityPipe`
* `\Shopware\Core\Content\ImportExport\Processing\Pipe\KeyMappingPipe`
* `\Shopware\Core\Content\ImportExport\Processing\Pipe\PipeFactory`

This class is now removed without replacement `\Shopware\Core\Content\ImportExport\Event\ImportExportAfterImportRecordEvent`.

This method is removed without replacement `\Shopware\Core\Content\ImportExport\Processing\Pipe\AbstractPipe::getDecorated()` cause the `AbstractPipe` class is now marked as internal.
