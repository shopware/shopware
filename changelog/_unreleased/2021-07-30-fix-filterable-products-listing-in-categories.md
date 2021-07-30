---
title: Fix filterable products listing in categories
issue: NEXT-16122
author: Jannis Leifeld
author_email: j.leifeld@shopware.com 
author_github: Jannis Leifeld
---
# Administration
* Changed pagination in `sw-cms-el-config-product-listing-config-filter-properties-grid` to real API response values
* Added data `{page, limit}` to the event `page-change` in `sw-cms-el-config-product-listing-config-filter-properties-grid`
* Changed limit in `productlisting/config/index.js` to 6 and page gets dynamically set
