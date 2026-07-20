---
title: Fix media thumbnail generation with null media thumbnail size
author: Benjamin Wittwer
author_email: Discord.Benjamin@web.de
author_github: gecolay
---
# Core
* Changed `Shopware\Core\Content\Media\Thumbnail\ThumbnailService` to correctly re-generate thumbnails with a null `mediaThumbnailSizeId`
