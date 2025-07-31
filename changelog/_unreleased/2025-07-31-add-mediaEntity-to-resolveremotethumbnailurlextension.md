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
