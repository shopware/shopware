---
title: Restructuring of category module
issue: NEXT-10540
author: Krispin Lütjann & Max Stegmeyer
---
# Core
* Added Navigation settings to `SalesChannelDefinition` and `SalesChannelTranslationDefinition`
* Added `navigationCategorySalesChannels` to `CmsPageDefinition`
* Added `EntryPointValidation` for making sure that main categories of Sales Channels cannot be links.
* Added `sw_breadcrumb_full` twig filter
* Added temporarily twig filter `sw_breadcrumb_build_types`
* Deprecated twig filters, use new `sw_breadcrumb_full` instead:
    * `sw_breadcrumb`
    * `sw_breadcrumb_types`
    * `sw_breadcrumb_build_types`
* Added internal link settings to `CategoryDefinition` and `CategoryTranslationDefinition`
* Added `CategoryUrlGenerator` to render different category link types
___
# Administration
* Added `sw-category-detail-products` component in following files:
    * `src/Administration/Resources/app/administration/src/module/sw-category/view/sw-category-detail-products/index.js`
    * `src/Administration/Resources/app/administration/src/module/sw-category/view/sw-category-detail-products/sw-category-detail-products.html.twig`
    * `src/Administration/Resources/app/administration/src/module/sw-category/view/sw-category-detail-products/sw-category-detail-products.scss`
* Added `sw-category-detail-seo` component in following files:
    * `src/Administration/Resources/app/administration/src/module/sw-category/view/sw-category-detail-seo/index.js`
    * `src/Administration/Resources/app/administration/src/module/sw-category/view/sw-category-detail-seo/sw-category-detail-seo.html.twig`
    * `src/Administration/Resources/app/administration/src/module/sw-category/view/sw-category-detail-seo/sw-category-detail-seo.scss`
* Changed location of product assignment in `sw-category-detail-base` to separate `sw-category-detail-products` component
* Changed location of seo & seo urls in `sw-category-detail-base` to separate `sw-category-detail-seo` component
* Changed location of layout assignment in `sw-category-detail-base` to `sw-category-detail-cms` component
* Added three new components:
    * `sw-category-entry-point-card`
    * `sw-category-entry-point-overwrite-modal`
    * `sw-category-sales-channel-multi-select`
* Added entry point overwrite check on save to `sw-category-detail`
* Added validation for navigation categories to `sw-category-tree`
* Added function for highlighting to `sw-tree-item`
* Refactored `mainNavigationCriteria` in `sw-sales-channel-detail-base` to allow entry points as main navigation entries
* Added internal link settings to `sw-category-link-settings`
* Added new computed properties to `sw-category-link-settings/index.js`
    * `linkTypeValues`
    * `entityValues`
    * `mainType`
    * `isInternal`
    * `isExternal`
* Added component `sw-category-entry-point-modal`
* Changed `sw-cms-layout-modal` component to also provide the layout itself with the `modal-layout-select` event as second argument.
  Internally the methods `onSelection` and `selectItem` now receive the layout itself as a new parameter.
  Also there is new data for the selected layout named `selectedPageObject` which is send with the `modal-layout-select` event as a second argument
  (the first argument is still the layout id).
* Added new block `sw_category_view_column_info` and style file to `sw-category-view` component:
    * `src/Administration/Resources/app/administration/src/module/sw-category/component/sw-category-view/sw-category-view.scss`
___
# Storefront
* Removed service menu from top bar
* Added service category listing to all viewports in footer
* Changed footer headlines to be clickable as page / list
* Added labeling and toggling of Home button in Navigation
* Added possibility to overwrite Home cms page and meta data per Sales Channel
* Added `category_url` function for rendering category urls for better link type handling
* Changed occurrences of category url generation in multiple templates
* Deprecated `layout/header/actions/service-menu-widget.html.twig`, menu has been moved to the bottom
* Deprecated block `layout_header_top_bar_service` in `layout/header/top-bar.html.twig`
* Changed category loading to prevent routing of categories with category type `folder` (only if they are not the main category) and `link`
* Changed the link of categories with type `link` in the breadcrumb to work properly
* Deprecated multiple variables in `layout/breadcrumb.html.twig`
* Changed the link of categories which point to a main navigation / the home page to have the right URL
