---
title: Fix Thumbnail generation when physical file is missing
issue: NEXT-7754
author: David Fecke
author_github: @leptoquark1
---
# Core
* Fix `updateThumbnails` function in `src/Core/Content/Media/Thumbnail/ThumbnailService.php` so that the physical presence of the thumbnail file is respected
* Added new parameter `$strict` to method `Shopware\Core\Content\Media\Thumbnail\ThumbnailService::updateThumbnails`
* Added option `--strict` to command `media:generate-thumbnails`. Supplying it, a physical file check for existing thumbnails is performed.
