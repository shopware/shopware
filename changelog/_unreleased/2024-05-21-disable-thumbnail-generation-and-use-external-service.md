---
title: Disable thumbnail generation and use external service
issue: NEXT-34463
---
# Core
* Changed `src/Core/Content/Media/Commands/GenerateThumbnailsCommand.php` to early return if remote thumbnails is enabled
* Changed `src/Core/Content/Media/Message/GenerateThumbnailsHandler.php` to early return if remote thumbnails is enabled
* Changed `src/Core/Content/Media/Thumbnail/ThumbnailService.php` to throw an exception if remote thumbnails is enabled
* Changed `persistFileToMedia` method in `src/Core/Content/Media/File/FileSaver.php` to disable thumbnail generation if remote thumbnails is enabled
* Added `src/Core/Content/Media/Core/Application/RemoteThumbnailLoader.php` service to load remote thumbnails
* Added `shopware.media.remote_thumbnails.enable`, `shopware.media.remote_thumbnails.pattern` parameters to `src/Core/Framework/Resources/config/packages/shopware.yaml` to allow configuration of remote thumbnail generation
___
# Upgrade Information

Thumbnail handling performance can now be improved by using remote thumbnails.

## Remote Thumbnail Configuration

To use remote thumbnails, you need to adjust the following parameters in your `shopware.yaml`:

1. `shopware.media.remote_thumbnails.enable`: Set this parameter to `true` to enable the use of remote thumbnails.

2. `shopware.media.remote_thumbnails.pattern`: This parameter defines the URL pattern for your remote thumbnails. Replace it with your actual URL pattern.
   
This pattern supports the following variables:
   *  `mediaUrl`: The base URL of the media file.
   *  `mediaPath`: The path of the media file relative to the mediaUrl.
   *  `width`: The width of the thumbnail.
   *  `height`: The height of the thumbnail.
   *  `mediaUpdatedAt`: The unix timestamp of the last media change.

For example, consider a scenario where you want to generate a thumbnail with a width of 80px.
With the pattern set as `{mediaUrl}/{mediaPath}?width={width}&ts={mediaUpdatedAt}`, the resulting URL would be `https://yourshop.example/abc/123/456.jpg?width=80&ts=1718954838`.
