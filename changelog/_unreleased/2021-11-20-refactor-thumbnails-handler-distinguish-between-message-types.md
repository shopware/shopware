---
title: Refactor GenerateThumbnailsHandler to distinguish between message types
author: David Fecke
author_github: @leptoquark1
---
# Core
* Fix different behaviour or results expected when `media:generate-thumbnails` command is executed with or without the `async` flag.
* The `GenerateThumbnailsHandler` now distinguish which method is executed in the`ThumbnailService` based on the message type.
* Added two methods `isStrict` and `setIsStrict` and it's corresponding property `isStrict` to class `UpdateThumbnailsMessage`
* The `GenerateThumbnailsHandler` will now proceed with `ThumbnailService::generate` when handling `GenerateThumbnailsMessage`
* The `GenerateThumbnailsHandler` will proceed with `ThumbnailService::updateThumbnails` when handling `UpdateThumbnailsMessage`
* When processed by MessageBus the optional strict flag for thumbnail generation (as introduced in _NEXT-7754_) will be passed,
  to the respective `ThumbnailService::updateThumbnails` method as expected. So it may considered in following generation process.
