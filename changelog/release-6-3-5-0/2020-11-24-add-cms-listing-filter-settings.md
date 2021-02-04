---
title: add cms listing filter settings
issue: NEXT-11355
author: Markus Velt
author_email: m.velt@shopware.com 
author_github: @raknison
---
# Administration
* Added new component `sw-cms-el-config-product-listing-config-filter-properties-grid` into `Resources/app/administration/src/module/sw-cms/elements/product-listing/config/components/sw-cms-el-config-product-listing-config-filter-properties-grid`
* Added new method `loadFilterableProperties` in `Resources/app/administration/src/module/sw-cms/elements/product-listing/config/index.js`
* Added new method `sortProperties` in `Resources/app/administration/src/module/sw-cms/elements/product-listing/config/index.js`
* Added new method `isActiveFilter` in `Resources/app/administration/src/module/sw-cms/elements/product-listing/config/index.js`
* Added new method `updateFilters` in `Resources/app/administration/src/module/sw-cms/elements/product-listing/config/index.js`
* Added new method `unpackFilters` in `Resources/app/administration/src/module/sw-cms/elements/product-listing/config/index.js`
* Added new method `onFilterProperties` in `Resources/app/administration/src/module/sw-cms/elements/product-listing/config/index.js`
* Added new method `onPageChange` in `Resources/app/administration/src/module/sw-cms/elements/product-listing/config/index.js`
* Added new method `propertyStatusChanged` in `Resources/app/administration/src/module/sw-cms/elements/product-listing/config/index.js`
* Added computed property `propertyRepository` in `Resources/app/administration/src/module/sw-cms/elements/product-listing/config/index.js`
* Added computed property `propertyCriteria` in `Resources/app/administration/src/module/sw-cms/elements/product-listing/config/index.js`
* Added computed property `filterByManufacturer` in `Resources/app/administration/src/module/sw-cms/elements/product-listing/config/index.js`
* Added computed property `filterByRating` in `Resources/app/administration/src/module/sw-cms/elements/product-listing/config/index.js`
* Added computed property `filterByPrice` in `Resources/app/administration/src/module/sw-cms/elements/product-listing/config/index.js`
* Added computed property `filterByFreeShipping` in `Resources/app/administration/src/module/sw-cms/elements/product-listing/config/index.js`
* Added computed property `filterByProperties` in `Resources/app/administration/src/module/sw-cms/elements/product-listing/config/index.js`
* Added new data prop `filters` in `Resources/app/administration/src/module/sw-cms/elements/product-listing/config/index.js`
* Added new data prop `filterPropertiesTerm` in `Resources/app/administration/src/module/sw-cms/elements/product-listing/config/index.js`
* Added new data prop `properties` in `Resources/app/administration/src/module/sw-cms/elements/product-listing/config/index.js`
* Added default config `filters` in `Resources/app/administration/src/module/sw-cms/elements/product-listing/index.js`
* Added default config `propertyWhitelist` in `Resources/app/administration/src/module/sw-cms/elements/product-listing/index.js`
