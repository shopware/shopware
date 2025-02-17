---
title: Add custom_field storeApiAware field
author: Benjamin Wittwer
author_email: benjamin.wittwer@a-k-f.de
author_github: akf-bw
---
# Core
* Added `storeApiAware` property and its corresponding getter and setter methods to the `Shopware\Core\System\CustomField\CustomFieldEntity`
* Added `ResetInterface` to the `Shopware\Core\System\SalesChannel\Api\StructEncoder`, to reset the class state after request
* Changed `Shopware\Core\System\SalesChannel\Api\StructEncoder` to fetch blocked custom fields & exclude them from the store API response
___
# Administration
* Added `sw_custom_field_detail_modal_store_api_aware` and `sw_custom_field_detail_modal_allow_cart_expose` blocks to `sw-custom-field-detail.html.twig`
