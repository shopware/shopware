---
title: fix cms block removable check
author: Felix Schneider
author_email: felix@wirduzen.de
author_github: @schneider-felix
---
# Administration
* Added new function `getItemName` in  `administration/Resources/app/administration/src/module/sw-category/view/sw-category-detail-products/index.js` to fetch the variant product name from parent product, when the product name for the variant is empty (eg. inheritance)
* Changed block `sw_category_detail_product_assignment_column_name` in `administration/Resources/app/administration/src/module/sw-category/view/sw-category-detail-products/sw-category-detail-products.html.twig` to fetch the product name with the new function `getItemName`
