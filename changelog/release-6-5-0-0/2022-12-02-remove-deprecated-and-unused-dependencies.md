---
title: Remove deprecated and unused dependencies
issue: NEXT-18421
---
# Storefront
* Removed deprecated polyfill NPM packages and their respective imports from `Resources/app/storefront/src/helper/polyfill-loader.helper.js`:
    * Removed `object-fit-images`
    * Removed `form-association-polyfill`
    * Removed `mdn-polyfills`
    * Removed `picturefill`
    * Removed `element-closest`
    * Removed `formdata-polyfill`
    * Removed `object-fit-polyfill`
    * Removed `intersection-observer`
    * Removed `report-validity`
* Removed unused NPM packages
    * Removed `nunito-fontface`
    * Removed `exports-loader`
    * Removed `imports-loader`
    * Removed `optimize-css-assets-webpack-plugin`
    * Removed `sass-resources-loader`
    * Removed `stylelint-webpack-plugin`
    * Removed `copy-webpack-plugin`
