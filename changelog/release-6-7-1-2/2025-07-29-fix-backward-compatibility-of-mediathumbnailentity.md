---
title: Fix backward compatibility of MediaThumbnailEntity
issue: 10040
author: Dominik Grothaus
author_email: d.grothaus@shopware.com
---
# Core
* Changed `$mediaId` of `Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity` from `string` to `?string` to have the same behaviour like in Shopware 6.6 so that objects serialized in Shopware 6.6 can be unserialized in 6.7 and don't throw an exception.
