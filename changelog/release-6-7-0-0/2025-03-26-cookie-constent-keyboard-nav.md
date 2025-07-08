---
title: Improve cookie settings accessibility
issue: #7624
---
# Storefront
* Changed `layout/cookie/cookie-configuration-group.html.twig` by wrapping the expand icon of the cookie groups in a proper button, including necessary accessibility attributes, to allow proper keyboard navigation.
* Removed the individual click handling in `cookie-configuration.plugin.js` for the cookie group collapse panels and replaced it with the native Bootstrap collapse component by simply adding the necessary attributes on the button element.
  * Removed method `_handleWrapperTrigger()` and corresponding event listeners.
  * Removed option `wrapperToggleSelector` because it became obsolete.
* Removed unnecessary styling in `scss/layout/_cookie-configuration.scss` that got obsolete from removing the custom click handler.
