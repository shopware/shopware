---
title: Text block translation from product pages
issue: NEXT-27273
---
# Administration
* Changed to use `value` instead of `v-model` in `src/Administration/Resources/app/administration/src/module/sw-cms/elements/text/config/sw-cms-el-config-text.html.twig`
* Added a method `elementUpdate` in `src/Administration/Resources/app/administration/src/module/sw-product/view/sw-product-detail-layout/index.js`
___
# Upgrade Information
* Changed behavior of text block element, which is able to switch between languages in product detail page
