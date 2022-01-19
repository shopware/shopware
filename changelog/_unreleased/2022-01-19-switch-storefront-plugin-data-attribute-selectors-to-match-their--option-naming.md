---
title: Switch storefront plugin data-attribute selectors to match their -option naming
issue: NEXT-19709
flag: NEXT_19709
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Storefront
* Deprecated storefront javascript plugin selector from `data-search-form` to `data-search-widget` behind feature flag NEXT_19709 and removed with update to 6.5.0 to improve developer experience for using storefront javascript plugin configurations
* Deprecated storefront javascript plugin selector from `data-offcanvas-cart` to `data-off-canvas-cart` behind feature flag NEXT_19709 and removed with update to 6.5.0 to improve developer experience for using storefront javascript plugin configurations
* Deprecated storefront javascript plugin selector from `data-collapse-footer` to `data-collapse-footer-columns` behind feature flag NEXT_19709 and removed with update to 6.5.0 to improve developer experience for using storefront javascript plugin configurations
* Deprecated storefront javascript plugin selector from `data-offcanvas-menu` to `data-off-canvas-menu` behind feature flag NEXT_19709 and removed with update to 6.5.0 to improve developer experience for using storefront javascript plugin configurations
* Deprecated storefront javascript plugin selector from `data-offcanvas-account-menu` to `data-account-menu` behind feature flag NEXT_19709 and removed with update to 6.5.0 to improve developer experience for using storefront javascript plugin configurations
* Deprecated storefront javascript plugin selector from `data-offcanvas-tabs` to `data-off-canvas-tabs` behind feature flag NEXT_19709 and removed with update to 6.5.0 to improve developer experience for using storefront javascript plugin configurations
* Deprecated storefront javascript plugin selector from `data-offcanvas-filter` to `data-off-canvas-filter` behind feature flag NEXT_19709 and removed with update to 6.5.0 to improve developer experience for using storefront javascript plugin configurations
___
# Upgrade Information

When you use one of the following attributes in CSS selectors, Javascript DOM elements selectors or HTML you have to switch them and/or respect the feature flag:

For Javascript:
```javascript
import Feature from 'src/helper/feature.helper';
if (Feature.isActive('NEXT_19709')) {
    /* do something with new selector */
} else {
    /* do something with old selector */
}
```

For twig templates:
```twig
{% if feature('NEXT_19709') %}
    {# do something with new selector #}
{% else %}
    {# do something with old selector #}
{% endif %}
```

-------------
| old | new |
| :-- | :-- |
| data-search-form | data-search-widget |
| data-offcanvas-cart | data-off-canvas-cart |
| data-collapse-footer | data-collapse-footer-columns |
| data-offcanvas-menu | data-off-canvas-menu |
| data-offcanvas-account-menu | data-account-menu |
| data-offcanvas-tabs | data-off-canvas-tabs |
| data-offcanvas-filter | data-off-canvas-filter |
-------------
