---
title: mark Import\Export internal
issue: NEXT-40446
---
# Core
* Added `Shopware\Core\Content\ImportExport\Event\ImportExportAfterProcessFinishedEvent.php`
* Deprecated `Shopware\Core\Content\ImportExport\Event\ImportExportAfterImportRecordEvent.php` which will be removed in 6.7.0.0 without replacement
* Deprecated the following classes which will marked as internal in 6.7.0.0
  * `Shopware\Core\Content\ImportExport\ImportExport.php`
  * `Shopware\Core\Content\ImportExport\Processing\Pipe\AbstractPipe.php`
  * `Shopware\Core\Content\ImportExport\Processing\Pipe\AbstractPipeFactory.php`
  * `Shopware\Core\Content\ImportExport\Processing\Pipe\ChainPipe.php`
  * `Shopware\Core\Content\ImportExport\Processing\Pipe\EntityPipe.php`
  * `Shopware\Core\Content\ImportExport\Processing\Pipe\KeyMappingPipe.php`
  * `Shopware\Core\Content\ImportExport\Processing\Pipe\PipeFactory.php`
___
# Next Major Version Changes
## These classes are now marked as internal
* `Shopware\Core\Content\ImportExport\ImportExport.php`
* `Shopware\Core\Content\ImportExport\Processing\Pipe\AbstractPipe.php`
* `Shopware\Core\Content\ImportExport\Processing\Pipe\AbstractPipeFactory.php`
* `Shopware\Core\Content\ImportExport\Processing\Pipe\ChainPipe.php`
* `Shopware\Core\Content\ImportExport\Processing\Pipe\EntityPipe.php`
* `Shopware\Core\Content\ImportExport\Processing\Pipe\KeyMappingPipe.php`
* `Shopware\Core\Content\ImportExport\Processing\Pipe\PipeFactory.php`

## This class is now removed without replacement
* `Shopware\Core\Content\ImportExport\Event\ImportExportAfterImportRecordEvent.php`

## This method is removed without replacement
* `\Shopware\Core\Content\ImportExport\Processing\Pipe\AbstractPipe::getDecorated()` cause the `AbstractPipe` class is now marked as internal
