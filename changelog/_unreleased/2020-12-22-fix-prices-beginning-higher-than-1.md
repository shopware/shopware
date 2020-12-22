---
title:              Fix prices beginning higher than 1
issue:              NEXT-12753
author:             Sebastian König
author_email:       s.koenig@tinect.de
author_github:      @tinect
---
#  Core
*  Changed `Content\Product\SalesChannel\Price\ProductPriceDefinitionBuilder` to respect prices with quantity starting higher than one
   * Added reusable variable `marchingRulePrices`
   * Changed variable `quantity` to be overwritten to respect the correct minimum purchase
   * Added new parameter `marchingRulePrices` to private method `buildPriceDefinitions`
   * Added new parameter `marchingRulePrices` to private method `buildPriceDefinition`
   * Added new parameter `marchingRulePrices` to private method `buildListingPriceDefinition`
   * Added new parameter `marchingRulePrices` to private method `buildPriceDefinitionForQuantity`
   * Added new private methods `getMinPurchase` and `getMinPurchaseByPrices` to determ the correct MinPurchase quantity by product and rule prices
*  Changed `Content\Test\Product\SalesChannel\ProductPriceDefinitionBuilderTest` to respect prices with quantity starting higher than one
   * Added new private methods `prepareProduct`, `prepareProductWithMultipleRulePrices`, `prepareProductWithRulePrices` to have static and reusable products for testing
   * Added new test `testProductPriceDefinitionNotContainPricesLowerMinPurchase`
