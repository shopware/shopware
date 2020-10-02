---
title:              Fix listing price view
issue:              NEXT-10598
author:             Oliver Skroblin
author_email:       o.skroblin@shopware.com
author_github:      @OliverSkroblin
---
# Core
* Changed `\Shopware\Core\Content\Product\ProductEntity::$grouped` flag behavior. The flag is now set over a global event subscriber when a product is loaded in a sales channel context.
* Removed custom handling of `\Shopware\Core\Content\Product\ProductEntity::$grouped` in `\Shopware\Core\Content\Product\Cms\ProductSliderCmsElementResolver`
___
# Storefront
* Added `product.isGrouped` check in listing price view
