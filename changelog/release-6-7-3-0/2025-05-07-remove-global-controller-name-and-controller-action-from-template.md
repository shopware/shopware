---
title: Remove global controllerName and controllerAction variables from templates
issue: 7422
jira_issue: NEXT-39807
author: Martin Bens
author_email: m.bens@shopware.com
author_github: @spigandromeda
---
# Storefront
* Added `is-active-route-{activeRoute}` CSS classes based on the active route to HTML body tag in `base.html.twig`, `single-cms-page.html.twig` and `error-maintenance.html.twig`. New CSS classes added to `_cart.scss`, `_checkout.scss`, `_confirm.scss`, `_finish.scss` and `_register.scss`.
* Changed `showLineItemModal` condition in `product.html.twig` to use the active route instead of the `controllerName` and `controllerAction` variables.
* Removed the usage of `controllerName` and `controllerAction` in JS for analytics and replaced it with `activeRoute`. Old variables are kept but deprecated.
* Deprecated `controllerName` and `controllerAction` variables in `TemplateDataExtension`.
___
# Upgrade Information
## Migration from controller variables to activeRoute
Replace `controllerName` and `controllerAction` with `activeRoute`:
* Twig: Use `activeRoute` instead of `controllerName`/`controllerAction`
* CSS: Use `.is-active-route-*` instead of `.is-ctl-*` and `.is-act-*`
* JS: Use `window.activeRoute` instead of `window.controllerName`/`window.actionName`
* Routes use dots, CSS classes use dashes: `activeRoute|replace({'.': '-'})`
___
# Next Major Version Changes
## Removal of deprecated controller variables
The following will be removed in Shopware 6.8.0:
* Twig variables `controllerName` and `controllerAction`
* CSS classes `is-ctl-*` and `is-act-*`
* JavaScript window properties `window.controllerName` and `window.actionName`
