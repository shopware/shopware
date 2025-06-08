---
title: Resolve Template Inheritance only in renderView for Storefront
issue: NEXT-17275
---
# Storefront
* Changed `StorefrontController` to resolve template inheritance only in `renderView`.
* Changed `StorefrontRenderEvent` in `StorefrontController::renderStorefront` to only get the base template as view from v6.5.0.0.  
___
# Upgrade Information
## StorefrontRenderEvent Changed
If you use the `StorefrontRenderEvent` you will get the original template as the `view` parameter instead of the inheriting template from v6.5.0.0
Take this in account if your subscriber depends on the inheriting template currently.
