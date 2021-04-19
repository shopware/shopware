---
title: Fix selected review state on mobile offcanvas not showing
issue: NEXT-13836
---
# Storefront
* Changed `replaceElement` method in `src/Storefront/Resources/app/storefront/src/helper/element-replace.helper.js` to handle if both source and target element is NodeList.
* Changed `replaceSelector` option in `src/Storefront/Resources/views/storefront/component/review/review-widget.html.twig` to replace a whole review offcanvas after updating review filter.
* Changed `replaceSelector` in `src/Storefront/Resources/views/storefront/page/product-detail/review/review-widget.html.twig` to replace a whole review offcanvas after updating review filter.
