---
title: Disable CSS auto prefixer
issue: NEXT-25150
---
# Storefront
* Changed constructor of `\Shopware\Storefront\Theme\ThemeCompiler` to accept config value `storefront.theme.auto_prefix_css` as 14th argument.
* Changed `\Shopware\Storefront\Theme\ThemeCompiler`for not using `\Padaliyajay\PHPAutoprefixer\Autoprefixer` support on default.
* Added new configuration key `storefront.theme.auto_prefix_css` in `Storefront/Resources/config/packages/storefront.yaml` which defaults to `false` to toggle `\Padaliyajay\PHPAutoprefixer\Autoprefixer` support.
