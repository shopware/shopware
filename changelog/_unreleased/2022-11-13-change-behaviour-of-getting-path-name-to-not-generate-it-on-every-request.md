---
title: Change behaviour of getting path name to not generate it on every request
issue: NEXT-0
author: tinect
author_email: s.koenig@tinect.de
author_github: tinect
---

# Core
* Added `path` as new StringField to `MediaDefinition` and `MediaThumbnailDefinition`
* Added `path` as new StringField to `MediaEntity` and `MediaThumbnailEntity`
* Changed `Content/Media/DataAbstractionLayer/MediaIndexer` to set field `path` of `media` and `media_thumbnail`
* Changed relative paths of `Content/Media/Pathname/UrlGenerator` to use dedicated service `PathGenerator`
* Added `AbstractPathGenerator` and `PathGenerator` to generate paths for given media
* Changed functions `updateThumbnails`, `createThumbnailsForSizes`, `ensureConfigIsLoaded` and `getImageResource` of `Content/Media/Thumbnail/ThumbnailService` to use static path of entity
