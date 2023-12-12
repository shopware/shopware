---
title: Improve storefront render exception
issue: NEXT-00000
author: Benjamin Wittwer
author_email: dev@a-k-f.de
author_github: akf-bw
---
# Core
* Changed `renderView` function call parameters in `StorefrontController`
* Added `renderViewException` function in `StorefrontException`
* Changed `cannotRenderView` in `StorefrontException` to `deprecated`
* Added `testRenderViewException` in `StorefrontExceptionTest` to match the changes from `StorefrontException`
