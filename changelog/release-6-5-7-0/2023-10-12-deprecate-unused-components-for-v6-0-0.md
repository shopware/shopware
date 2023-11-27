---
title: Deprecate unused components for cleanup in v6.6.0
issue: NEXT-30171
---
# Storefront
* Deprecated custom `Ellipsis` implementation (Read more / read less) because it is not used anymore. Please use Bootstrap collapse with additional CSS instead.
    * Deprecated JS-plugin `Resources/app/storefront/src/plugin/ellipsis/ellipsis.plugin.js` will be removed without replacement. Plugin is not used anymore.
    * Deprecated template `Resources/views/storefront/component/ellipsis/ellipsis.html.twig` will be removed without replacement. Corresponding JS-Plugin `fading.plugin.js` is not used anymore.
    * Deprecated SCSS file `Resources/app/storefront/src/scss/component/_ellipsis.scss` will be removed without replacement. Corresponding JS-Plugin `ellipsis.plugin.js` is not used anymore.
* Deprecated custom `Fading` implementation (Read more / read less) because it is not used anymore. Please use Bootstrap collapse with additional CSS instead.
    * Deprecated JS-plugin `Resources/app/storefront/src/plugin/fading/fading.plugin.js` will be removed without replacement. Plugin is not used anymore.
    * Deprecated SCSS file `Resources/app/storefront/src/scss/component/_fading-plugin.scss` will be removed without replacement. Corresponding JS-Plugin `fading.plugin.js` is not used anymore.
* Deprecated unused styling for `cart-item`. Will be removed because the corresponding templates were removed in v6.5.0. Please build upon the CSS for `.line-item` instead. 
    * Deprecated SCSS file `Resources/app/storefront/src/scss/page/checkout/_aside-children.scss`. 
    * Deprecated unused CSS rules for `.checkout-aside-item` in `Resources/app/storefront/src/scss/page/checkout/_aside.scss`.
    * Deprecated unused CSS rules for `.cart-item` in `Resources/app/storefront/src/scss/layout/_offcanvas-cart.scss`.
* Deprecated Helper `Resources/app/storefront/src/plugin/cms-slot-reload/helper/cms-slot-option-validator.helper.js`. Will be removed without replacement. Helper is not used anymore.
* Deprecated Service `Resources/app/storefront/src/plugin/cms-slot-reload/service/cms-slot-reload.service.js`. Will be removed without replacement. Service is not used anymore.
