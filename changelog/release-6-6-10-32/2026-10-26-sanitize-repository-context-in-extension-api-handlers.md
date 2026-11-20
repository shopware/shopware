---
title: Sanitize repository context in extension API handlers
issue: https://github.com/shopware/shopware-private/issues/487
---

# Administration

* Changed the extension API handlers `repositoryGet` and `repositoryClone` to filter the context of the resolved result instead of the pending promise.
  Extensions receive only the context properties they passed themselves.
* Changed the extension API repository handlers to always remove `authToken` from the context returned to the extension, also when the extension passes it.
* Changed the extension API handlers `repositorySave`, `repositorySaveAll` and `repositoryDelete` to resolve with `null` instead of the raw request response.
