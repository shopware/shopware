---
title: Do not show fullwidth OffCanvas in mobile (xs) viewport
issue: NEXT-23633
---
# Storefront
* Changed the opening OffCanvas behaviour in the following files and always omit the `fullwidth` parameter for small viewports:
    * `account-menu.plugin.js`
    * `offcanvas-tabs.plugin.js`
    * `offcanvas-menu.plugin.js`
    * `offcanvas-cart.plugin.js`
    * `cookie-configuration.plugin.js`
* Added new bootstrap SCSS variable overrides to style the OffCanvas instead of manual CSS overrides:
    * `$offcanvas-padding-y: $grid-gutter-width / 2 !default;`
    * `$offcanvas-padding-x: $grid-gutter-width / 2 !default;`
    * `$offcanvas-border-width: 0 !default;`
    * `$offcanvas-border-color: transparent !default;`
* Removed styling for outdated `offcanvas-content-container` class, Bootstrap class `offcanvas-body` is used since 6.5.0.0
