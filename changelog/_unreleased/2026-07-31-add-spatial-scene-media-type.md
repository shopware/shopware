---
title: Add spatial scene media type
issue: NEXT-00000
author: ffrank913
author_email: f.frank@shopware.com
author_github: @ffrank913
---
# Core
* Added `Shopware\Core\Content\Media\MediaType\SpatialSceneType` to mark media entities that represent a spatial scene. Media of this type intentionally never carries a file, so it is never assigned by a `TypeDetectorInterface` — those only run on file uploads — and has to be set explicitly on write.
___
# Administration
* Added `src/core/service/utils/media-type.utils.ts` with `isFilelessMediaType()` and `FILELESS_MEDIA_TYPES`, which tell a media type that never carries a file apart from an upload that is actually broken.
* Changed `sw-media-quickinfo` to hide the missing-file banner for media types that never carry a file.
* Changed `sw-media-preview-v2` to render a type icon instead of the broken-file icon for media types that never carry a file.
* Added method `mediaItemName` to `sw-media-media-item`, which falls back to the media title when there is no file name to show. The item name and its tooltip now use it, so media without a file no longer renders a `null.null` tooltip.
