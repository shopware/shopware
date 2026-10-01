---
title: Keep non-ASCII characters in uploaded media file names
issue: 20681
---
# Core
* Changed `Shopware\Core\Content\Media\Api\MediaUploadController` and `Shopware\Core\Content\Media\Subscriber\MediaCreationSubscriber` to strip only control characters and invisible Unicode format characters (e.g. zero-width space, soft hyphen, bidi overrides) from media file names and paths. Non-ASCII characters such as umlauts are kept, so `Erdmännchen.jpg` is no longer stored as `Erdmnnchen.jpg`.
* Changed the media upload, rename and provide-name actions to reject file names that are not valid UTF-8 with `CONTENT__MEDIA_ILLEGAL_FILE_NAME`.
