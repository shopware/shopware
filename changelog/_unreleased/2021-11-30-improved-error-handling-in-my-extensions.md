---
title: Improved error handling in "My extensions"
issue: NEXT-19085
author: Maike Sestendrup
author_email: m.sestendrup@shopware.com 
---
# Administration
* Changed `sw-extension-card-bought/index.js` to use the translator for displaying an installation error
* Added a new `StoreError` to `extension-error-handler.service.js` for the `FRAMEWORK__PLUGIN_REQUIREMENT_MISMATCH` exception
