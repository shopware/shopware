---
title: fix-immediately-download-media-after-renaming
issue: 12148
author: rittou
author_email: rittou.xiii@gmail.com
author_github: @rittou
---
# Core
* **Changed return type of `Shopware\Core\Content\Media\Api\MediaUploadController::renameMediaFile()`** from `Symfony\Component\HttpFoundation\Response` to `Symfony\Component\HttpFoundation\JsonResponse`
  * The method now returns structured JSON data with the new media path instead of a redirect response
  * Response format: `{"mediaPath": "/path/to/renamed/media/file"}`

* **Changed return type of `Shopware\Core\Content\Media\File\FileSaver::renameMedia()`** from `void` to `string`
  * The method now returns the new file path of the renamed media
  * This enables immediate access to the updated file path without additional API calls
