---
title: Fix type check in seo resolver in store-api
issue: NEXT-13817
---
# Core
* Changed method `findStruct` in `StoreApiSeoResolver` to fix encoding of non-Struct response elements, like error messages
