---
title: Preview entity does nothing on listing page layouts
issue: NEXT-19403
author: Niklas Limberg
author_email: n.limberg@shopware.com
author_github: NiklasLimberg
---
# Administration
* Deprecated the template ref `cmsDemoSelection` in `sw-cms-sidebar.html.twig` as it's unused
* Added constant `TYPE_MAPPING_ENTITIES` to `sw-cms.constant.js`
* Deprecated the computed `cmsTypeMappingEntities` in `sw-cms-detail/index.js` as it's replaced by the constant above
* Changed `product-listing/component/index.js` to display the `currentDemoProducts` in the `cmsPageState`
* Changed `sw-cms-detail/index.js` to load up to 8 demo-products in given category
* Added the property `currentDemoProducts` and the required mutations to the `cms-page.state.js`
