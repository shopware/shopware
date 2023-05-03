---
title: Update to webpack 5
issue: NEXT-22223
---
# Storefront
* Changed webpack to version 5 and update build related loaders and plugins as well as other packages
    * Changed `webpack` version to `5.75.0`
    * Changed `webpack-cli` version to `5.0.1`
    * Changed `webpack-dev-server` version to `4.11.1`
    * Changed `webpack-merge` version to `5.8.0`
    * Changed `webpack-bundle-analyzer` version to `4.7.0`
    * Changed `mini-css-extract-plugin` version to `2.7.2`
    * Changed `terser-webpack-plugin` version to `5.3.6`
    * Changed `babel-eslint` version to `10.1.0`
    * Changed `babel-loader` version to `9.1.2`
    * Changed `@babel/preset-typescript` version to `7.18.6`
    * Changed `file-loader` version to `6.2.0`
    * Changed `sass` version to `1.57.1`
    * Changed `sass-loader` version to `13.2.0`
    * Changed `style-loader` version to `3.3.1`
    * Changed `postcss-loader` version to `7.0.2`
    * Changed `postcss-pxtorem` version to `6.0.0`
    * Changed `autoprefixer` version to `10.4.13`
    * Changed `fs-extra` version to `11.1.0`
    * Changed `query-string` version to `7.1.3`
    * Removed outdated package `@nuxtjs/friendly-errors-webpack-plugin`
    * Removed outdated package `friendly-errors-webpack-plugin`
    * Removed unused package `expose-loader`
    * Removed unused package `popper.js`, Bootstrap uses `@popperjs/core` as peerDependency
    * Removed outdated package `babel-eslint`
    * Added `@babel/eslint-parser` package instead of `babel-eslint`
* Changed `webpack.config.js` to be compatible with webpack 5
