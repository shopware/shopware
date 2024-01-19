---
title: Upload and usage of spatial objects as media
issue: NEXT-29686
author: Viktor Buzyka
author_email: v.buzyka@shopware.com
---
# Core
* Added static method `getMimeType` to `Shopware\Core\Content\Media\File\FileInfoHelper` for more accurate detection of mime type
* Added `Shopware\Core\Content\Media\MediaType\SpatialObjectType` to support spatial objects in media
* Added `Shopware\Core\Content\Media\TypeDetector\SpatialObjectTypeDetector`
* Added property `spatialObjectType` to `Shopware\Core\Content\Media\MediaEntity` to store spatial object configuration  
* Added property `config` to `Shopware\Core\Content\Media\MediaDefinition` 
* Added method `invalidateMedia` to `Shopware\Core\Framework\Adapter\Cache\CacheInvalidationSubscribe` to cache for products that use updated media objects
___
# API
* Added `config` field to `media` entity
___
# Administration
* Added computed property `isSpatial`, `getSpatialIconName` and `getSpatialSubline` to `sw-media-base-item/index.js`
* Added `placeHolderThumbnails` `glb` and `octet-stream` to `sw-media-preview-v2/index.js`
* Added property `model` to property `placeHolderThumbnails` to `sw-media-preview-v2/index.js`
* Added property `arReady` to media component `/sidebar/sw-media-quickinfo/index.js`
* Changed detection `mimeType` for `glb`-files in `media.api.service.js`
* Added `neutral-reversed` style variant for `sw-label` component
* Added props to detect if `sw-product-image` is spatial and/or 'ar ready'
___
# Storefront
* Added new plugin class `spatial-ar-viewer-plugin.ts`
* Added new plugin class `spatial-base-viewer.plugin.ts`
* Added new plugin class `spatial-gallery-slider-viewer.plugin.ts`
* Added new plugin class `spatial-zoom-gallery-slider-viewer.plugin.ts`
* Changed `cms-element-image-gallery.html.twig` to support `glb`-files in the slider
* Added template `utilities/ar-overlay.html.twig` with block `augmented_reality_overlay` to render AR overlay.
* Added template `utilities/qr-code-modal.html.twig` with block `spatial_ar_qr_code_modal` to render modal with QR code for AR rendering on mobile devices.
