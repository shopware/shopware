---
title: Replace serialize/unserialize with JsonSerializable
issue: NEXT-26752
---
# Core
* Deprecated `contextData` property and `withContext`, `readContext` methods and will be removed in version 6.6 in `src/Core/Content/Media/Message/GenerateThumbnailsMessage.php`
* Added `context` property and corresponding getter / setter in `src/Core/Content/Media/Message/GenerateThumbnailsMessage.php`
___
# Upgrade Information
The `context` property is used instead of `contextData` property in `src/Core/Content/Media/Message/GenerateThumbnailsMessage` due to the `context` data is serialized in context source
