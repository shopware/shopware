---
title: Do not show skip to content when clicking scroll up button
issue: #4666
---
# Storefront
* Added new option `topElementId` to `ScrollUpPlugin` that allows configuring the top element that should be focused after scrolling up.
* Changed `ScrollUpPlugin` to focus the configured `topElementId` instead of the first focus-able element.