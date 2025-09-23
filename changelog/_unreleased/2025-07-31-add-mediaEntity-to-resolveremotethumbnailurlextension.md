---
title: Add MediaEntity to ResolveRemoteThumbnailUrlExtension
author: Sascha Heilmeier
author_email: sascha.heilmeier@netlogix.de
author_github: @scarbous
---
# Core
* Added `Shopware\Core\Content\Media\MediaEntity` to `Shopware\Core\Content\Media\Extension\ResolveRemoteThumbnailUrlExtension` to allow individual media handling.
* Added `mimeType` to `Shopware\Core\Content\Media\Core\Params\UrlParams` 
* Changed `Shopware\Core\Content\Media\Extension\ResolveRemoteThumbnailUrlExtension` to allow to skip thumbnail loading by returning `null` as result

___

# Upgrade Information

## Deprecation of properties in `ResolveRemoteThumbnailUrlExtension`

The properties `mediaPath` and `mediaUpdatedAt` from `Shopware\Core\Content\Media\Extension\ResolveRemoteThumbnailUrlExtension` are deprecated and will be removed with the next major version. Set the values directly into the newly added `mediaEntity` property.

## Deprecation of `media` and `thumbnail` in `MediaPathChangedEvent`

The method `media` from `Shopware\Core\Content\Media\Event\MediaPathChangedEvent` is deprecated and will be removed with the next major version. Use the newly added `mediaWithMimeType` method instead.

The method `thumbnail` from `Shopware\Core\Content\Media\Event\MediaPathChangedEvent` is deprecated and will be removed with the next major version. Use the newly added `thumbnailWithMimeType` method instead.

___

# Next Major Version Changes

## Removal of properties in `ResolveRemoteThumbnailUrlExtension`

The properties `$mediaPath` and `$mediaUpdatedAt` from `Shopware\Core\Content\Media\Extension\ResolveRemoteThumbnailUrlExtension` were removed. Set the values directly into the `mediaEntity` property.
