---
title: Add feature flag support for Storefront SCSS
issue: NEXT-19448
author: Tobias Berge
author_email: t.berge@shopware.com
author_github: @tobiasberge
---
# Core
* Changed `composer.json` script `build:js` and added command `@php bin/console feature:dump`
___
# Storefront
* Added `\Shopware\Storefront\Theme\ThemeCompiler::getFeatureConfigScssMap` which is used to provide a SCSS map of the current feature config
* Added `scssFeatureConfig` to `app/storefront/webpack.config.js` which is used to provide a SCSS map of the current feature config
* Added new SCSS function `feature()` in `app/storefront/src/scss/abstract/functions/feature.scss` which is used to check for features in the current feature config
