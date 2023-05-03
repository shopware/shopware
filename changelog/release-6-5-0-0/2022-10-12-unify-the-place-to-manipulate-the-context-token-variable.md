---
title: Unify the place to manipulate the context token variable
issue: NEXT-17944
---
# Core
* Removed the context token from the response body. Use the header to manipulate the context token (`sw-context-token`)
___
# Upgrade Information
Since v6.6.0.0, `ContextTokenResponse` class won't return the contextToken value in the response body anymore, please using the header `sw-context-token` instead
