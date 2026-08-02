---
title: Dispatch MediaFileExtensionWhitelistEvent only once per info config request
author: Matthias Breddin
author_email: mb@lunetics.com
author_github: @lunetics
---
# Core
* Changed `InfoController::config()` to resolve the private media extension whitelist once via `MediaFileExtensionListProvider::getMimeTypesByExtension()` and derive `private_allowed_extensions` from its keys. Previously it also called `getAllowedExtensions()`, which `getMimeTypesByExtension()` already calls internally, dispatching `MediaFileExtensionWhitelistEvent` twice per request.
