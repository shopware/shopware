---
title: Add filter panel to the customer module
issue: NEXT-13309
---
# Administration
* Added data `filterCriteria`, `defaultFilters` in `src/module/sw-customer/page/sw-customer-list/index.js`
* Added computed `listFilters` in `src/module/sw-customer/page/sw-customer-list/index.js`
* Added `defaultCriteria` watch property in `src/module/sw-customer/page/sw-customer-list/index.js`
* Added method `updateCriteria` in `src/module/sw-customer/page/sw-customer-list/index.js`
* Changed computed `defaultCriteria` in `src/module/sw-customer/page/sw-customer-list/index.js`
* Changed `{% block sw_customer_list_sidebar_filter %}` in `src/module/sw-customer/page/sw-customer-list/sw-customer-list.twig.html`
