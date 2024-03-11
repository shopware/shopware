---
title: Improve storefront render exception
issue: NEXT-32331
author: Benjamin Wittwer
author_email: dev@a-k-f.de
author_github: akf-bw
---
# Core
* Changed `renderView` function call parameters in `StorefrontController` to call `renderViewException` instead of `cannotRenderView`
* Added `renderViewException` function in `StorefrontException`
* Deprecated `cannotRenderView` in `StorefrontException`
