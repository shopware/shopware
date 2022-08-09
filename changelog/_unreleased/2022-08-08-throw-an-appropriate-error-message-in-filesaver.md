---
title: Throw an appropriate error message in FileSaver
issue: NEXT-XXX
author: tinect
author_email: s.koenig@tinect.de
author_github: tinect
---

# Core

* Changed Content\Media\Exception\FileTypeNotSupportedException
  * Added second parameter to specify the given extension
  * Changed message to throw the relevant error with info about the extension
* Added Content\Media\Exception\ThumbnailNotSupportedException for not supported file to generate thumbnail for
* Changed Content\Media\Thumbnail\ThumbnailService
  * Changed method generateThumbnails to throw ThumbnailNotSupportedException instead of FileTypeNotSupportedException
  * Changed method updateThumbnails to throw ThumbnailNotSupportedException instead of FileTypeNotSupportedException
