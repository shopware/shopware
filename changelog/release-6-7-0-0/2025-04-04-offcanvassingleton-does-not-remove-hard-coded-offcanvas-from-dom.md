---
title: OffCanvasSingleton does not remove hard-coded offcanvas from DOM
issue: #4450
---
# Storefront
* Added new class `js-offcanvas-singleton` to `OffCanvasSingleton` in order to identify Bootstrap OffCanvas created by `OffCanvasSingleton`.
* Changed `OffCanvasSingleton::getOffCanvas` to query elements with selector `.js-offcanvas-singleton` instead of generic `.offcanvas`.