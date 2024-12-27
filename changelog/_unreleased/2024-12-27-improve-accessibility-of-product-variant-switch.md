---
title: Improve accessibility of product variant switch
issue: 5193
---
# Storefront
* Changed element `product-detail-configurator-group` from `<div>` to `<fieldset>` in `Resources/views/storefront/component/buy-widget/configurator.html.twig`
* Changed element `product-detail-configurator-group-title` from `<div>` to `<legend>` in `Resources/views/storefront/component/buy-widget/configurator.html.twig`
* Deprecated return value of `isCombinableCls` variable in `Resources/views/storefront/component/buy-widget/configurator.html.twig`. Will return string `not-combinable disabled` instead of boolean `false`.