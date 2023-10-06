---
title: Deprecate dependencies
issue: NEXT-18418
author: Tobias Berge
author_email: t.berge@shopware.com
author_github: @tobiasberge
---
# Storefront
* Deprecated the following polyfill dependencies because the IE11 support will be discontinued
    * `form-association-polyfill`
    * `mdn-polyfills/NodeList.prototype.forEach`
    * `mdn-polyfills/CustomEvent`
    * `mdn-polyfills/MouseEvent`
    * `picturefill`
    * `element-closest`
    * `formdata-polyfill`
    * `object-fit-polyfill`
    * `intersection-observer`
    * `report-validity`
* Deprecated the following webpack loaders because they are unused
    * `imports-loader`
    * `exports-loader`
    * `sass-resources-loader`
* Deprecated the following webpack plugins because they are unused
    * `stylelint-webpack-plugin`
    * `copy-webpack-plugin`
    * `optimize-css-assets-webpack-plugin`
