---
title: Fix SCSS Valiator to save original color functions
issue: #11526
---
# Storefront
* Changed `Shopware\Storefront\Theme\Validator\SCSSValidator` to save original color function and not compiled values. Added extra validation for RGB and HSL values.
___
# Administration
* Changed `administration/src/modules/sw-theme-manager/page/sw-theme-manager-detail/index.js` to show correctly translated error messages for theme manager form fields.