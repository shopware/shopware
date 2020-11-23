---
title: Fixed EXIF detection during thumbnail generation for remote filesystems
issue: NEXT-12290
---
# Core
* Changed `\Shopware\Core\Content\Media\Thumbnail\ThumbnailService::getImageResource()`-method to use in-memory stream to read EXIF-metadata instead of filepath, as this will also work for remote filesystems.
