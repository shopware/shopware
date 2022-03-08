---
title: Price indication regulation Guideline
issue: NEXT-19582
author: Ramona Schwering
author_email: r.schwering@shopware.com
author_github: leichteckig
---
# Core
* Added new class `Shopware\Core\Checkout\Cart\Price\Struct\RegulationPrice`
* Added property `regulationPrice` in `Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice`
* Added property `regulationPrice` in `Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition`
* Added property `regulationPrice` in `Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price`
* Added method `Shopware\Core\Checkout\Cart\Price\GrossPriceCalculator::calculateRegulationPrice()`
* Added method `Shopware\Core\Checkout\Cart\Price\NetPriceCalculator::calculateRegulationPrice()`
* Added method `Shopware\Core\Content\Product\SalesChannel\Price\ProductPriceCalculator::getRegulationPrice()`
* Changed `Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\PriceFieldSerializer` to decode the regulationPrice
___
# API
*
___
# Administration
* Added property `hideRegulationPrices` in `src/app/component/form/sw-list-price-field/index.js`
* Added computed `regulationPrice`, `defaultRegulationPricePrice`, `regulationPriceHelpText` in `src/app/component/form/sw-list-price-field/index.js`
* Added method `regulationPriceChanged` in `src/app/component/form/sw-list-price-field/index.js`
* Changed `src/module/sw-bulk-edit/page/sw-bulk-edit-product/index.js` to support regulation prices 
* Added block `sw_list_price_field_regulation_price` in `src/app/component/form/sw-list-price-field/sw-list-price-field.html.twig`
* Changed method `removePriceInheritation` in `src/module/sw-product/component/sw-product-price-form/index.js`
* Added snippet `global.sw-list-price-field.labelRegulationPriceGross`
* Added snippet `global.sw-list-price-field.helpTextRegulationPriceGross`
* Added snippet `global.sw-list-price-field.labelRegulationPriceNet`
* Added snippet `sw-bulk-edit.product.prices.purchasePrices.changeLabel`
* Added snippet `sw-bulk-edit.product.prices.purchasePrices.placeholderPurchasePrices`
___
# Storefront
* Added element for previous given price in the following components:
  * `src/Storefront/Resources/views/storefront/component/buy-widget/buy-widget-price.html.twig`
  * `src/Storefront/Resources/views/storefront/component/product/block-price.html.twig`
  * `src/Storefront/Resources/views/storefront/page/product-detail/buy-widget-price.html.twig`
* Added element for previous given price in the following components:
  * `platform/src/Storefront/Resources/views/storefront/component/product/card/price-unit.html.twig`
  * `platform/src/Storefront/Resources/views/storefront/layout/header/search-suggest.html.twig`
