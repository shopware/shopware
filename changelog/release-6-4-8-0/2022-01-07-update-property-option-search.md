---
title: Update property option search 
issue: NEXT-18069
author: Simon Vorgers
author_email: s.vorgers@shopware.com
author_github: SimonVorgers
---
# Administration
* Added property `propertiesAvailable` to `module/sw-product/component/sw-product-add-properties-modal/index.js` component.
* Deprecated data properties in `module/sw-product/component/sw-product-add-properties-modal/index.js` component.
  * `propertiesTotal`
  * `isPropertiesLoading`
  * `selectedProperty`
  * `selectedProperty`
  * `propertyValuesTotal`
  * `isPropertyValuesLoading`
  * `propertiesPage`
  * `propertiesLimit`
  * `propertyValuesPage`
  * `propertyValuesPage`
  * `isSelectable`
  * `searchTerm`
* Deprecated methods `getProperties` in `module/sw-product/component/sw-product-add-properties-modal/index.js` component.
  * `onSelectProperty`
  * `getPropertyValues`
  * `onSelectPropertyValue`
  * `onChangePageProperties`
  * `onChangePagePropertyValues`
  * `onChangeSearchTerm`
* Deprecated computed in `module/sw-product/component/sw-product-add-properties-modal/index.js` component.
  * `propertyGroupRepository`
  * `propertyGroupCriteria`
  * `propertyGroupOptionRepository`
* Added styles to `sw-product-add-properties-modal.scss`.
* Deprecated methods in `module/sw-product/component/sw-product-properties/index.js` component.
  * `updateNewPropertiesItem`
  * `addNewPropertiesItem`
* Changed methods in `module/sw-product/component/sw-product-properties/index.js` component.
  * `updateNewProperties` using EntityCollection instead of custom collection
  * `onSaveAddPropertiesModal` using now with EntityCollection instead of custom collection
* Deprecated script twig blocks inside `sw-product-add-properties-modal.html.twig`
  * Deprecated block `sw_product_add_properties_modal_filled_state_search_empty` - Use block `sw_product_add_properties_modal_filled_state_search_empty` inside `Resources/views/storefront/layout/meta.html.twig` instead
  * Deprecated block `sw_product_add_properties_modal_filled_state_search_empty_image` - Use block `sw_product_add_properties_modal_filled_state_search_empty_image` inside `Resources/views/storefront/layout/meta.html.twig` instead
  * Deprecated block `sw_product_add_properties_modal_filled_state_search_empty_image` - Use block `` inside `Resources/views/storefront/layout/meta.html.twig` instead
  * Deprecated block `sw_product_add_properties_modal_filled_state_body` - Use block `sw_property_search_tree` inside `Resources/views/storefront/layout/meta.html.twig` instead
  * Deprecated block `sw_product_add_properties_modal_filled_state_body_content` - Use block `sw_property_search_tree_container` inside `Resources/views/storefront/layout/meta.html.twig` instead
  * Deprecated block `sw_product_add_properties_modal_properties` - Use block `sw_property_search_tree_group_grid_columns` inside `Resources/views/storefront/layout/meta.html.twig` instead
  * Deprecated block `sw_product_add_properties_modal_properties_name` - Use block `sw_property_search_tree_group_grid_columns_name` inside `Resources/views/storefront/layout/meta.html.twig` instead
  * Deprecated block `sw_product_add_properties_modal_properties_assigned` - Use block `sw_property_search_tree_group_grid_columns_options` inside `Resources/views/storefront/layout/meta.html.twig` instead
  * Deprecated block `sw_product_add_properties_modal_properties_pagination` - Use block `sw_property_search_tree_group_grid_pagination` inside `Resources/views/storefront/layout/meta.html.twig` instead
  * Deprecated block `sw_product_add_properties_modal_values` - Use block `sw_product_add_properties_modal_values` inside `Resources/views/storefront/layout/meta.html.twig` instead
  * Deprecated block `sw_product_add_properties_modal_values_name` - Use block `sw_product_add_properties_modal_values_name` inside `Resources/views/storefront/layout/meta.html.twig` instead
  * Deprecated block `sw_product_add_properties_modal_values_pagination` - Use block `sw_property_search_tree_option_grid_pagination` inside `Resources/views/storefront/layout/meta.html.twig` instead
  * Deprecated block `sw_product_add_properties_modal_values_empty_primary`.
  * Deprecated block `sw_product_add_properties_modal_values_empty_secondary`.
  * Deprecated block `sw_product_add_properties_modal_values_loading` - Use block `` inside `Resources/views/storefront/layout/meta.html.twig` instead
* Added `propertiesAvailable` property to the `sw-product-add-properties-modalg` in `sw-product-properties.html.twig`
* Removed deprecated properties from `sw-product-properties.html.twig`
* Deprecated methods in `module/sw-product/view/sw-product-detail-variants/index.js` component.
  * `updateNewPropertiesItem`
  * `addNewPropertiesItem`
* Changed methods in `module/sw-product/view/sw-product-detail-variants/index.js` component.
  * `updateNewProperties` using EntityCollection instead of custom collection
  * `onSaveAddPropertiesModal` using now with EntityCollection instead of custom collection
* Removed deprecated properties from `sw-product-detail-variants.html.twig`
* Removed following snippets:
  * `sw-product.properties.titleEmptyStateList`
  * `sw-product.properties.placeholderSearchProperties`
  * `sw-product.properties.addPropertiesModal.titleEmptyPropertyValues`
  * `sw-product.properties.addPropertiesModal.titleEmptyPropertyValues`
  * `sw-product.properties.addPropertiesModal.titleNoPropertySelected`