---
title: Bootstrap v5 OffCanvas markup and CSS
issue: NEXT-18368
author: Tobias Berge
author_email: t.berge@shopware.com
author_github: @tobiasberge
---
# Storefront
* Changed default option value `offCanvasPosition` to `start` in `Resources/app/storefront/src/plugin/cookie/cookie-configuration.plugin.js`
* Changed default option value `offCanvasPosition` to `start` in `Resources/app/storefront/src/plugin/header/account-menu.plugin.js`
* Changed default option value `position` to `start` in `Resources/app/storefront/src/plugin/main-menu/offcanvas-menu.plugin.js`
* Changed default option value `offcanvasPosition` to `end` in `Resources/app/storefront/src/plugin/offcanvas-cart/offcanvas-cart.plugin.js`
* Changed default option value `offcanvasPosition` to `end` in `Resources/app/storefront/src/plugin/offcanvas-tabs/offcanvas-tabs.plugin.js`
* Added twig block `utilities_offcanvas_header` and wrapper element `.offcanvas-header` in `Resources/views/storefront/utilities/offcanvas.html.twig`
* Deprecated class `.offcanvas-content-container` in favor of `.offcanvas-body` in `Resources/views/storefront/utilities/offcanvas.html.twig`
