---
title: Throw an appropriate error message in FileSaver
issue: NEXT-XXX
author: tinect
author_email: s.koenig@tinect.de
author_github: tinect
---

# Core

* Deprecated Content\Media\Exception\FileTypeNotSupportedException, use FileExtensionNotSupportedException or ThumbnailNotSupportedException instead
* Added Content\Media\Exception\FileExtensionNotSupportedException as replacement of FileTypeNotSupportedException
* Added Content\Media\Exception\ThumbnailNotSupportedException for not supported file to generate thumbnail for
* Changed Content\Media\Thumbnail\ThumbnailService
  * Changed method generateThumbnails to throw ThumbnailNotSupportedException instead of FileTypeNotSupportedException
  * Changed method updateThumbnails to throw ThumbnailNotSupportedException instead of FileTypeNotSupportedException
* Changed Shopware\Core\Content\Media\File\FileSaver
    * Changed method persistFileToMedia to throw FileExtensionNotSupportedException instead of FileTypeNotSupportedException
