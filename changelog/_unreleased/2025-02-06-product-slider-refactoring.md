---
title: Product Slider Refactoring
issue: NEXT-38373
author: Martin Krzykawski
author_email: m.krzykawski@shopware.com
author_github: @MartinKrzykawski
---
# Core
* Changed `Shopware\Core\Content\Product\Cms\ProductSliderCmsElementResolver` to improve abstraction and enable extensibility via event listeners for custom associations.
* Added `Shopware\Core\Content\Product\Cms\ProductSlider\AbstractProductSliderProcessor`, which splits the product slider logic into multiple processor classes for better separation of concerns.
* Added `Shopware\Core\Content\Product\Cms\ProductSlider\StaticProductProcessor` to handle static product sliders where products are selected manually.
* Added `Shopware\Core\Content\Product\Cms\ProductSlider\ProductStreamProcessor` to handle product sliders based on product streams.
* Deprecated `Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver::resolveDefinitionField`. This method will be removed in v6.8.0.0 without replacement.
* Deprecated `Shopware\Core\Content\Product\Cms\ProductSlider\ProductSliderCmsElementResolver::resolveCriteriaForLazyLoadedRelations`. This method will be removed in v6.8.0.0 without replacement.
