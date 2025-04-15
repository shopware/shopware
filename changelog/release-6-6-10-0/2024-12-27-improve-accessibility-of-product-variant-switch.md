---
title: Improve accessibility of product variant switch
issue: #5193
---
# Storefront
* Changed element `product-detail-configurator-group` from `<div>` to `<fieldset>` in `Resources/views/storefront/component/buy-widget/configurator.html.twig`.
* Changed element `product-detail-configurator-group-title` from `<div>` to `<legend>` in `Resources/views/storefront/component/buy-widget/configurator.html.twig`.
* Changed text content of `product-detail-configurator-option-label` to also render a `visually-hidden` alternative text for media and color variant previews in `Resources/views/storefront/component/buy-widget/configurator.html.twig`.
* Deprecated return value of `isCombinableCls` variable in `Resources/views/storefront/component/buy-widget/configurator.html.twig`. Will return string `not-combinable disabled` instead of boolean `false`.
* Deprecated custom styling for `product-detail-configurator-group-*`. Will use Bootstrap `btn-check` component and helper classes instead.