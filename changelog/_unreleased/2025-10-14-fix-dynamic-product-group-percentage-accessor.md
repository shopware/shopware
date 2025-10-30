---
title: Fix percentage ratio dynamic product groups
issue: 12996
---
# Core
* Changed `Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPriceAccessorBuilder::buildAccessor` to ignore zero-valued entries so dynamic product group conditions based on percentage ratios evaluate correctly again.
* Added the `symfony/polyfill-php85` dependency to make it possible to use PHP 8.5 features.
___
# Upgrade Information
## Added PHP 8.5 polyfill
The new dependency `symfony/polyfill-php85` was added, to make it possible to already use PHP 8.5 features, like `array_first` and `array_last`
