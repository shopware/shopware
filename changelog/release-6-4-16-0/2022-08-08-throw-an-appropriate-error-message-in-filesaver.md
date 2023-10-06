---
title: Throw an appropriate error message in FileSaver
issue: NEXT-22824
author: tinect
author_email: s.koenig@tinect.de
author_github: tinect
---
# Core
* Deprecated `Content\Media\Exception\FileTypeNotSupportedException`, use `FileExtensionNotSupportedException` or `ThumbnailNotSupportedException` instead
* Added `Content\Media\Exception\FileExtensionNotSupportedException` as replacement of `FileTypeNotSupportedException`
* Added `Content\Media\Exception\ThumbnailNotSupportedException` for files not supporting thumbnail generation
* Changed method `Content\Media\Thumbnail\ThumbnailService::generateThumbnails` to throw `ThumbnailNotSupportedException` instead of `FileTypeNotSupportedException`
* Changed method `Content\Media\Thumbnail\ThumbnailService::updateThumbnails` to throw `ThumbnailNotSupportedException` instead of `FileTypeNotSupportedException`
* Changed method `Shopware\Core\Content\Media\File\FileSaver::persistFileToMedia` to throw `FileExtensionNotSupportedException` instead of `FileTypeNotSupportedException`
