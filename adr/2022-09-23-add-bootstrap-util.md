---
title: Add bootstrap JS-plugin initialization utility to storefront JS
date: 2022-09-23
area: storefront
tags: [storefront, javascript, bootstrap]
---

## Context

* Some Bootstrap JavaScript plugins have to be initialized manually to the desired DOM elements, see: https://getbootstrap.com/docs/4.3/components/tooltips/#example-enable-tooltips-everywhere
* This is not needed for all Bootstrap plugins. Modals for example work out of the box without extra initialization.
* Currently, we only initialize Tooltips using `src/utility/tooltip/tooltip.util.js`
* On dynamic content changes (listing pagination, ajax OffCanvas cart, etc.) Bootstrap plugins like Tooltip are no longer working.
* For example: It is not possible to show Tooltips in the OffCanvas cart without extra/manual work in JavaScript.

## Decision

* Add a new module `src/utility/bootstrap/bootstrap.util` in favor of `TooltipUtil` to consider more Bootstrap plugins in the future.
* Currently, it initializes `Tooltip` and `Popover` because those are the only Bootstrap plugins which have a documented manual initialization.
* We use the "selector" option in order to initialize Bootstrap plugins on selectors, which are added dynamically to the HTML. See: https://getbootstrap.com/docs/4.3/components/tooltips/#options

## Consequences

* In the main.js, `BootstrapUtil.initBootstrapPlugins()` is used instead of `new TooltipUtil()` to initialize Popovers as well.
* `TooltipUtil` is deprecated.
* Since we use event delegation ("selector" option) inside `BootstrapUtil` we don't need to manually re-initialize the Bootstrap plugins after dynamic content changes, 
  so it works automatically for all `[data-toogle="tooltip"]` and `[data-toogle="popover"]` selectors.
