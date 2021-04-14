---
title:              Optimize thumbnail generation performance
issue:              NEXT-14411
author:             OliverSkroblin
author_email:       o.skroblin@shopware.com
author_github:      @OliverSkroblin
---
# Core
* Added `\Shopware\Core\Content\Media\Thumbnail\ThumbnailService::generate` to generate thumbnails for multiple entities at once
* Deprecated `\Shopware\Core\Content\Media\Thumbnail\ThumbnailService::generateThumbnails`, use `\Shopware\Core\Content\Media\Thumbnail\ThumbnailService::generate` instead
