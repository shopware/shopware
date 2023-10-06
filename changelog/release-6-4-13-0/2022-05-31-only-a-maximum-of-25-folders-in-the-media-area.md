---
title: Serverside searching for properties
issue: NEXT-20441
author: Niklas Limberg
author_email: n.limberg@shopware.com
author_github: NiklasLimberg
---
# Administration
* Changed the `propertyCriteria` computed in `sw-cms/elements/product-listing/config/index.js` include the search term in the criteria
* Deprecated the `displayedProperties` computed in `sw-cms/elements/product-listing/config/index.js` instead use properties directly
* Changed `sw-cms-el-config-product-listing.html.twig` to directly use `properties` instead of the `displayedProperties`
* Changed `sw-cms-el-config-product-listing.html.twig` to use the default `sw-empty-state`
* Removed `.sw-cms-element-product-listing-config-filter__empty-state` and `.sw-cms-element-product-listing-config-filter__empty-state--label` in `sw-cms-el-config-product-listing.scss` in favour of the default `sw-empty-state`
* Deprecated the `sw-cms-el-config-product-listing-config-filter-properties-grid` component in favour of integrating it directly into the `sw-cms-el-config-product-listing` component.
