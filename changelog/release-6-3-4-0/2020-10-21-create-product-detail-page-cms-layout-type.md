---
title: Create "Product Detail Page" CMS layout type
issue: NEXT-11500
---
# Administration
*  Added block `{% block sw_cms_create_wizard_page_type_options_product_detail %}` in `module/sw-cms/component/sw-cms-create-wizard/sw-cms-create-wizard.html.twig`
*  Changed `sortPageTypes()` computed property in `src/Administration/Resources/app/administration/src/module/sw-cms/page/sw-cms-list/index.js` to add new 'Product Pages' item on tab list
*  Changed `pageTypes()` computed property in `src/Administration/Resources/app/administration/src/module/sw-cms/page/sw-cms-list/index.js` to add new 'Product Pages' category on grid display
*  Changed `pageTypeNames` data property in `module/sw-cms/component/sw-cms-create-wizard/index.js` to add new 'Product Pages' page type
*  Changed `pageTypeIcons` data property in `module/sw-cms/component/sw-cms-create-wizard/index.js` to add a new icon for 'Product Pages' page type
