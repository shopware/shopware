---
title: Add SCSS compiler interface
issue: NEXT-23976
---
# Storefront
* Added `Shopware\Storefront\Theme\AbstractScssCompiler` as a blueprint for custom scss compilers
* Added `Shopware\Storefront\Theme\ScssPhpCompiler` as a wrapper for `\ScssPhp\ScssPhp\Compiler`
* Added `AbstractScssCompiler` as argument for the constructor of `Shopware\Storefront\Theme\ThemeCompiler`
