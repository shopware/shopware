---
title: Add main module to app manifest
issue: NEXT-13986
---
# Core
* Added new element `main-module` to `admin` section in app manifests.
___
# Administration
* Changed behaviour of `shopwareExtensionService` to return link to main module instead of first module defined
* Changed properties of `sw-my-aps-page`. Module name is not required anymore.
* Changed behaviour `sw-my-aps-page`. If `moduleName` is omitted the main module is loaded.