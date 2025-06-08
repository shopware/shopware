---
title: Implement cms buy box element in Storefront
issue: NEXT-11747
---
# Core
* Added `Shopware\Core\Content\Product\Cms\BuyBoxCmsElementResolver` to resolve data for `buy-box` cms element.
* Added `Shopware\Core\Content\Cms\SalesChannel\Struct\BuyBoxStruct.php` to handle data for `buy-box` cms element.
___
# Storefront
* Added method `switchBuyBoxVariant` in `Storefront\Controller\CmsController.php` to handle product variant switch for buy box element
* Changed delivery information template in `Shopware\Storefront\Resources\views\storefront\component\delivery-information.html.twig` 
* Added cms buybox element template in `Shopware\Storefront\Resources\views\storefront\element\cms-element-buy-box.html.twig`
* Added buy widget template in `Shopware\Storefront\Resources\views\storefront\component\buy-widget\buy-widget.html.twig` 
* Added buy widget form template in `Shopware\Storefront\Resources\views\storefront\component\buy-widget\buy-widget-form.html.twig`
* Added buy widget price template in `Shopware\Storefront\Resources\views\storefront\component\buy-widget\buy-widget-price.html.twig`
* Added buy widget configurator template in `Shopware\Storefront\Resources\views\storefront\component\buy-widget\configurator.html.twig`
* Added new BuyBoxPlugin `Resources/app/storefront/src/plugin/buy-box/buy-box.plugin.js` to handle logic for buy box element
* Changed VariantSwitchPlugin `Resources/app/storefront/src/plugin/variant-switch/variant-switch.plugin.js` to handle product variant switch for buy box element
