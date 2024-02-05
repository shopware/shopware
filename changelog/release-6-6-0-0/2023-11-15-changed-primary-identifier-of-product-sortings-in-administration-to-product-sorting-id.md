---
title: Changed primary identifier of product sortings in administration to product sorting id
issue: NEXT-30554
author: Simon Fiebranz
author_email: s.fiebranz@shopware.com
author_github: CR0YD
---
# Core
* Changed the way of identification of product sortings in the database `system_config` and in `category_translation.slot_config` from the `url_keys` to the `id`'s
* Changed the identification of product sortings in `SortingListingProcessor.php`, so that product sorting ids can be processed
* Changed the processing of a custom default product sorting options in `ProductListingCmsElementResolver.php`
___
# Administration
* Changed save value of the default sorting option of a sales channel in `sw-settings-listing` from sorting option key to sorting option id
* Changed save value of the sorting options of a configuration in `src/Administration/Resources/app/administration/src/module/sw-cms/elements/product-listing/config` from sorting option key to sorting option id
* Added the manual input for a product sorting `url_key` in `sw-settings-listing-option-general-info`
* Added use of snippets for criteria of a product sorting in `src/Administration/Resources/app/administration/src/module/sw-cms/elements/product-listing/config/components/sw-cms-el-config-product-listing-config-sorting-grid/index.js`
