---
title: Product number range cannot be created
issue: NEXT-38717
---
# Administration
* Added the method `getProductNumberRanges` in `/module/sw-settings-number-range/page/sw-settings-number-range-create/index.js` to get product number range. 
* Changed the computed `numberRangeTypeCriteria` in `/module/sw-settings-number-range/page/sw-settings-number-range-detail/index.js` to filter the number range type.
* Changed the method `onChangeType` in `module/sw-settings-number-range/page/sw-settings-number-range-detail/index.js` to handle the change of number range type for product.
* Added the data `isShowProductWarning` and `hasProductNumberRange` in `module/sw-settings-number-range/page/sw-settings-number-range-create/index.js` to show the warning message for product number range.
* Added the method `onChangeType` in `module/sw-settings-number-range/page/sw-settings-number-range-create/index.js` to override the change of number range type for product in detail page.
* Added twig block `sw_settings_number_range_detail_content_global_product_warning` in `/module/sw-settings-number-range/page/sw-settings-number-range-detail` to show the warning message for product number range.
