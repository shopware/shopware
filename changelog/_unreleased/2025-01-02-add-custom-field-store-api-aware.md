---
title: Add custom_field storeApiAware field
author: Benjamin Wittwer
author_email: benjamin.wittwer@a-k-f.de
author_github: akf-bw
---
# Core
* Added `store_api_aware` (`storeApiAware`) bool field to the `custom_field` entity by migration (default value: `true`)
* Changed `Shopware\Core\System\CustomField\CustomFieldDefinition` to add the `store_api_aware` (`storeApiAware`) bool field
* Changed `Shopware\Core\System\CustomField\CustomFieldEntity` to add the `storeApiAware` field + get & set methods
* Changed `Shopware\Core\System\SalesChannel\Api\StructEncoder` to add the missing `ResetInterface` & `reset` method
* Changed `Shopware\Core\System\SalesChannel\Api\StructEncoder` to fetch blocked custom fields & exclude them from the store api response
___
# Administration
* Changed `src/module/sw-settings-custom-field/component/sw-custom-field-detail/sw-custom-field-detail.html.twig` to add the `storeApiAware` field in the `sw_custom_field_detail_modal_store_api_aware` block
* Changed `src/module/sw-settings-custom-field/component/sw-custom-field-detail.html.twig` to add the missing `sw_custom_field_detail_modal_allow_cart_expose` block
* Changed `src/module/sw-settings-custom-field/component/sw-custom-field-list/index.js` to set the default value for `storeApiAware`
