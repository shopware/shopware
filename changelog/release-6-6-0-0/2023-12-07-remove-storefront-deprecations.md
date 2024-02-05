---
title: Remove Storefront deprecations
issue: NEXT-32085
---
# Storefront
* Removed deprecated method `\Shopware\Storefront\Framework\Twig\IconExtension::getFinder`
* Removed deprecated const `\Shopware\Storefront\Framework\Routing\RequestTransformer::REQUEST_TRANSFORMER_CACHE_KEY`
* Changed parameter `$activeOnly` to be required in `\Shopware\Storefront\Theme\ConfigLoader\AbstractAvailableThemeProvider::load`
* Changed parameter `$activeOnly` to be required in `\Shopware\Storefront\Theme\ConfigLoader\DatabaseAvailableThemeProvider::load`
* Removed deprecated template `Resources/views/storefront/page/product-detail/index.html.twig`
* Deprecated event `\Shopware\Storefront\Event\ThemeCompilerConcatenatedScriptsEvent` without replacement. The concatenation of the `all.js` is no longer happening.
