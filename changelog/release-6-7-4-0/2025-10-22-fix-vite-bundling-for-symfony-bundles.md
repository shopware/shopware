---
title: Fix vite bundling for Symfony bundles
issue: #12165
---
# Administration
* Changed `loadExtensions()` in the `utils` Vite plugin to remove `bundle` suffix from the extension name folder, in line of what Symfony assets are doing, thus fixing an issue that lazy loading of components from plugins or bundles with `Bundle`-Suffix did not work.
___
# Core
* Changed `\Shopware\Administration\Framework\Twig\ViteFileAccessorDecorator::getContent()` to not remove `Bundle` suffix anymore on the fly, as the suffix is now already removed by vite itself and the generated entrypoints file already uses the correct path.