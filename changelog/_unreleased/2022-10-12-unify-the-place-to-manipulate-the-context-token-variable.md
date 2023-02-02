---
title: Unify the place to manipulate the context token variable
issue: NEXT-17944
---
# Core
* Using the header to manipulate the context token variable (sw-context-token), we won't return it inside a response body anymore
___
# Upgrade Information
Since v6.6.0.0, `ContextTokenResponse` class won't return the contextToken value in the response body anymore, please using the header `sw-context-token` instead