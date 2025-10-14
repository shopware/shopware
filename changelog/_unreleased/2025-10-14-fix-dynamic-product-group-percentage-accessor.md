---
title: Fix percentage ratio dynamic product groups
issue: 12996
---
# Core
* Changed `Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPriceAccessorBuilder::buildAccessor` to ignore zero-valued entries so dynamic product group conditions based on percentage ratios evaluate correctly again.
* Added the `symfony/polyfill-php85` dependency and moved affected internals to `array_last()` so pointer handling stays consistent across supported PHP versions.
___
# Upgrade Information
## array_last helper availability
All installations now ship with `symfony/polyfill-php85`, which exposes the native `array_last()` helper on PHP 8.2 and 8.3. Plugin developers can drop custom implementations and rely on the global `array_last()` function for consistent pointer handling across Shopware and their extensions.
