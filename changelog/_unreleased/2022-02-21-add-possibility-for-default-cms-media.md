---
title: Add possibility for default cms media
issue: NEXT-20073
---
# Core
* Added the possibility to resolve default Cms media via the filename.
* Changed `Shopware/Core/Content/Media/Cms/Type/ImageSliderTypeDataResolver.php` to be able to load images from `/bundles/storefront/assets/default/cms/{fileName}` in the storefront.
* Changed `Shopware/Core/Content/Media/Cms/ImageCmsElementResolver.php` to be able to load images from `/bundles/storefront/assets/default/cms/{fileName}` in the storefront.
* Changed `Shopware/Core/Content/Cms/DataAbstractionLayer/FieldSerializer/SlotConfigFieldSerializer.php` to allow `default` as a valid choice for fields.
___
# Storefront
*  Added the possibility to display default images via filename.
