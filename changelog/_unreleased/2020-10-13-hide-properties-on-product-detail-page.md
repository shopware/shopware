---
title: Hide properties on product detail page
issue: /
author: Rune Laenen
author_email: rune@laenen.nu 
author_github: @runelaenen
---
# Core
*  Added `visible_on_detail` field on `property_group` entity
___
# Administration
*  Added `sw_property_detail_filter_visible_container` and `sw_property_detail_base_visible_on_detail` blocks to `sw-property-detail-base`.
*  Added switch field for `propertyGroup.visibleOnDetail` in `sw-property-detail-base`
___
# Storefront
*  Added if-statement in `page_product_detail_properties_table_row` to filter out hidden properties
*  Added block `page_product_detail_properties_item` around label and value block within if.
