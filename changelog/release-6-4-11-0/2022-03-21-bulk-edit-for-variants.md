---
title: Bulk edit for variants
issue: NEXT-19387
---
# Administration
* Added `disabled` prop in `sw-multi-tag-select` component.
* Added `sw-bulk-edit-product-description` component in `sw-bulk-edit` module.
* Added `disabled` prop in `sw-bulk-edit-product-media` component.
* Added `disabled` prop in `sw-bulk-edit-product-visibility` component.
* Changed `onChangeValue` method in `sw-bulk-edit-change-type-field-renderer` component.
* Added the following methods in `sw-bulk-edit-change-type-field-renderer` component:
    * `onInheritanceRestore`
    * `onInheritanceRemove`
* Changed the following blocks in `sw-bulk-edit-change-type-field-renderer` component template:
    * `sw_bulk_edit_change_type_field_renderer_change_form_value_field`
    * `sw_bulk_edit_change_type_field_renderer_change_form_value_field_without_change_type`
* Changed `createdComponent` method in `sw-bulk-edit-customer` component.
* Added `setRouteMetaModule` method in `sw-bulk-edit-customer` component.
* Changed `sw_bulk_edit_customer_actions_save` block in `sw-bulk-edit-customer` component template.
* Changed `createdComponent` method in `sw-bulk-edit-order` component.
* Added `setRouteMetaModule` method in `sw-bulk-edit-order` component.
* Changed `sw_bulk_edit_order_actions_save` block in `sw-bulk-edit-order` component template.
* Added the following data variables in `sw-bulk-edit-product` component:
    * `parentProductFrozen`
    * `isComponentMounted`
* Removed `beforeDestroy` property in `sw-bulk-edit-product` component.
* Added `beforeUnmount` property in `sw-bulk-edit-product` component.
* Changed `createdComponent` method in `sw-bulk-edit-product` component.
* Added the following methods in `sw-bulk-edit-product` component:
    * `setRouteMetaModule`
    * `setBulkEditProductValue`
    * `getParentProduct`
    * `onInheritanceRestore`
    * `onInheritanceRemove`
    * `setProductPrice`
    * `setProductSearchKeywords`
    * `setProductProperties`
* Changed the following methods in `sw-bulk-edit-product` component:
    * `defineBulkEditData`
    * `definePricesBulkEdit`
    * `onProcessData`
    * `processListPrice`
    * `openModal`
* Added the following blocks in `sw-bulk-edit-product` component template:
    * `sw_bulk_edit_product_smart_bar_back`
    * `sw_bulk_edit_product_content_inheritance_card`
* Changed the following blocks in `sw-bulk-edit-product` component template:
    * `sw_bulk_edit_product_content_gereral_information`
    * `sw_bulk_edit_product_content_prices`
    * `sw_bulk_edit_product_content_property`
    * `sw_bulk_edit_product_content_deliverability`
    * `sw_bulk_edit_product_content_assignments`
    * `sw_bulk_edit_product_content_media_card`
    * `sw_bulk_edit_product_content_labelling`
    * `sw_bulk_edit_product_content_seo`
    * `sw_bulk_edit_product_content_meansures_packaging_card`
    * `sw_bulk_edit_product_content_essential_card`
    * `sw_bulk_edit_product_content_meansures_custom_field_card`
* Added the following props in `sw-product-properties` component:
    * `disabled`
    * `isAssociation`
    * `showInheritanceSwitcher`
* Added `showBulkEditModal` data variable in `sw-product-variant-modal` component.
* Added the following methods in `sw-product-variant-modal` component:
    * `toggleBulkEditModal`
    * `onEditItems`
* Changed `sw_product_variant_modal_body_grid_bulk` block in `sw-product-variant-modal` component template.
* Added `sw_product_variant_modal_bulk_edit_modal` block in `sw-product-variant-modal` component template.
* Added `showBulkEditModal` data variable in `sw-product-variants-overview` component.
* Added the following methods in `sw-product-variants-overview` component:
    * `toggleBulkEditModal`
    * `onEditItems`
* Added the following blocks in `sw-product-variants-overview` component template:
    * `sw_product_variants_overview_bulk`
    * `sw_product_variants_overview_bulk_edit_modal`
* Removed `beforeDestroy` property in `sw-product-detail` component.
* Added `beforeUnmount` property in `sw-product-detail` component.
* Changed `onBulkEditItems` method in `sw-product-list` component.
* Added `canSetLoadingRules` prop in `sw-product-detail-context-prices` component.
* Changed `mountedComponent` method in `sw-product-detail-context-prices` component to set rules loading if needed.
