---
title: Update npm dependencies
issue: NEXT-13084
author: Timo Altholtmann
 
---
# Administration
* Updated dependency `apexcharts` to `3.25.0`
* Updated dependency `axios` to `0.21.1`
* Updated dependency `copy-webpack-plugin` to `6.4.1`
* Updated dependency `dompurify` to `2.2.6`
* Updated dependency `lodash` to `4.17.21`
* Updated dependency `terser-webpack-plugin` to `4.2.3`
* Updated dependency `prismjs` to `1.23.0`
* Updated dev-dependency `sass-loader` to `8.0.2`
* Updated dev-dependency `stylelint` to `13.11.0`
* Removed dev-dependency `node-sass`, replaced by `sass`
* Added dev-dependency `sass@1.32.8`
___
# Storefront
* Updated dependency `copy-webpack-plugin` to `6.4.1`
* Updated dependency `terser-webpack-plugin` to `4.2.3`
* Updated dev-dependency `webpack-dev-server` to `3.11.2`
* Updated dev-dependency `stylelint` to `13.11.0`
* Updated dev-dependency `sass-loader` to `7.2.0`
* Removed dev-dependency `node-sass`, replaced by `sass`
* Added dev-dependency `sass@1.32.8`
___
# Upgrade Information
## NPM package copy-webpack-plugin update
This plugin has now version `6.4.1`, take a look at the [github changelog](https://github.com/webpack-contrib/copy-webpack-plugin/releases/tag/v6.0.0) for breaking changes.

## NPM package node-sass replacement
Removed `node-sass` package because it is deprecated. Added the `sass` package as replacement. For more information take a look [deprecation page](https://sass-lang.com/blog/libsass-is-deprecated).
