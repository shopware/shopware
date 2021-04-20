---
title: Implement layout assignment in Page builder
issue: NEXT-12967
---
# Administration
* Added component `sw-cms-product-assignment` in `src/module/sw-cms/component`
* Added `{% block sw_cms_layout_assignment_modal_info_text_product_detail_pages %}` in `src/module/sw-cms/component/sw-cms-layout-assignment-modal/sw-cms-layout-assignment-modal.html.twig`
* Added `{% block sw_cms_layout_assignment_modal_product_detail_pages_select %}` in `src/module/sw-cms/component/sw-cms-layout-assignment-modal/sw-cms-layout-assignment-modal.html.twig`
* Added `{% block sw_cms_layout_assignment_modal_confirm_changes_text_products %}` in `src/module/sw-cms/component/sw-cms-layout-assignment-modal/sw-cms-layout-assignment-modal.html.twig`
* Added `{% block sw_cms_layout_assignment_modal_confirm_changes_text_products_assigned_layouts %}` in `src/module/sw-cms/component/sw-cms-layout-assignment-modal/sw-cms-layout-assignment-modal.html.twig`
* Added computed `productColumns` in `src/module/sw-cms/component/sw-cms-layout-assignment-modal/index.js`
* Added computed `productCriteria` in `src/module/sw-cms/component/sw-cms-layout-assignment-modal/index.js`
* Added computed `isProductDetailPage` in `src/module/sw-cms/component/sw-cms-layout-assignment-modal/index.js`
* Added method `validateProducts` in `src/module/sw-cms/component/sw-cms-layout-assignment-modal/index.js`
* Added method `discardProductChanges` in `src/module/sw-cms/component/sw-cms-layout-assignment-modal/index.js`
