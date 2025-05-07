---
title: Remove global controllerName and controllerAction variables from templates
issue: 8285
author: Martin Bens
author_email: m.bens@shopware.com
author_github: @spigandromeda
---
# Storefront
* Added `is-active-route-{activeRoute}` CSS classes based on the active route to HTML body tag in `base.html.twig`, `single-cms-page.html.twig` and `error-maintenance.html.twig`. New CSS classes added to `_cart.scss`, `_checkout.scss`, `_confirm.scss`, `_finish.scss` and `_register.scss`.
* Changed `showLineItemModal` condition in `product.html.twig` to use the active route instead of the `controllerName` and `controllerAction` variables.
* Replaces the usage of `controllerName` and `controllerAction` in JS for analytics with `activeRoute`. Old variables are kept but deprecated.
* Global `controllerName` and `controller` variables deprecated in `TemplateDataExtension`.
