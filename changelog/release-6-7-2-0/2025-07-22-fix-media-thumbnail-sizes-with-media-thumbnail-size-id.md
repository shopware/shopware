---
title: Fix media thumbnail sizes with mediaThumbnailSizeId
author: Benjamin Wittwer
author_email: Discord.Benjamin@web.de
author_github: gecolay
---
# Core
* Added `mediaThumbnailSizeId` field to `Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailDefinition`
* Changed `Shopware\Core\Content\Media\Thumbnail\ThumbnailService` to correctly check thumbnail existing by size id & insert correct media with and height into database
