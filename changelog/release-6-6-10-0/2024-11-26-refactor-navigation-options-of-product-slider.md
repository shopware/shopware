---
title: Refactor navigation options of product slider
issue: NEXT-39635
author: Marcel Brode
author_email: m.brode@shopware.com
author_github: @Marcel Brode
---
# Administration
* Added `navigationArrows` property to product slider CMS element, to enable and configure navigation arrows
* Added `speed` and `autoplayTimeout` properties to product slider CMS element, to be able to configure the speed of the transition animation and the time between slide transitions
* Added multiple help texts and classes to `src/module/sw-cms/elements/product-slider/config/sw-cms-el-config-product-slider.html.twig` to match the usability of the image slider
* Changed `src/module/sw-cms/elements/image-slider/config/sw-cms-el-config-image-slider.html.twig` to visually match the configuration of the image slider
* Deprecated `navigation` property of product slider CMS element, use `navigationArrows` instead
* Deprecated `hasNavigation` computed property in `src/module/sw-cms/elements/product-slider/component/index.js`, use `hasNavigationArrows` instead
* Deprecated `sw_cms_element_product_slider_config_settings_navigation` block in `src/module/sw-cms/elements/product-slider/config/sw-cms-el-config-product-slider.html`, will be removed without replacement
___
# Storefront
* Added navigation arrow options to product slider CMS element to enable arrows outside or inside, like already in the image slider
* Changed `productSliderOptions` in `views/storefront/element/cms-element-product-slider.html.twig` to fix some display or usage issues, due to faulty configuration checks
