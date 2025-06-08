---
title: Changed error handling for ImportExport
issue: NEXT-33970
author: Simon Fiebranz
author_email: s.fiebranz@shopware.com
author_github: CR0YD
---
# Core
* Added the `ImportExportExceptionImportExportHandlerEvent`, which is dispatched before an error that occurred during the export or import is tried to be exported.
* Added the `ImportExportExceptionExportRecordEvent`, which is dispatched when a field of the currently processed entity could not be exported correctly.
* Added the method `exportExceptions` in `ImportExport` which exports the given exceptions to an invalid records file that is afterward assigned to the current `ImportExportLogEntity`.

* Deprecated class `\Shopware\Core\Content\ImportExport\Exception\FileEmptyException`. It will be removed, use `\Shopware\Core\Content\ImportExport\ImportExportException::fileEmpty` instead.
* Deprecated class `\Shopware\Core\Content\ImportExport\Exception\FileNotReadableException`. It will be removed, use `\Shopware\Core\Content\ImportExport\ImportExportException::fileNotReadable` instead.
* Deprecated class `\Shopware\Core\Content\ImportExport\Exception\InvalidFileContentException`. It will be removed, use `\Shopware\Core\Content\ImportExport\ImportExportException::invalidFileContent` instead.
* Deprecated class `\Shopware\Core\Content\ImportExport\Exception\ProfileWrongTypeException`. It will be removed, use `\Shopware\Core\Content\ImportExport\ImportExportException::profileWrongType` instead.
* Deprecated class `\Shopware\Core\Content\ImportExport\Exception\UnexpectedFileTypeException`. It will be removed, use `\Shopware\Core\Content\ImportExport\ImportExportException::unexpectedFileType` instead.
* Deprecated method `\Shopware\Core\Content\ImportExport\ImportExportException::fileEmpty`. Thrown exception will change from `FileEmptyException` to `ImportExportException`.
* Deprecated method `\Shopware\Core\Content\ImportExport\ImportExportException::fileNotReadable`. Thrown exception will change from `FileNotReadableException` to `ImportExportException`.
* Deprecated method `\Shopware\Core\Content\ImportExport\ImportExportException::invalidFileContent`. Thrown exception will change from `InvalidFileContentException` to `ImportExportException`.
* Deprecated method `\Shopware\Core\Content\ImportExport\ImportExportException::profileWrongType`. Thrown exception will change from `ProfileWrongTypeException` to `ImportExportException`.
* Deprecated method `\Shopware\Core\Content\ImportExport\ImportExportException::unexpectedFileType`. Thrown exception will change from `UnexpectedFileTypeException` to `ImportExportException`.
___
# Administration
* Changed the notifications in `sw-import-export-activity` so that they close after the default time.
* Added error messages with respective display handling for failed exports, with and without error logs, and for failed imports with error log in `sw-import-export-activity`.
