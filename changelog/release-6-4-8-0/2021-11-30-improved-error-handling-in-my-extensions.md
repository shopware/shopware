---
title: Improved error handling in "My extensions"
issue: NEXT-19085
author: Maike Sestendrup
author_email: m.sestendrup@shopware.com 
---
# Administration
* Changed `src/module/sw-extension/component/sw-extension-card-bought` to use the translator for displaying an installation error
* Added new `StoreError` `FRAMEWORK__PLUGIN_REQUIREMENT_MISMATCH` in `src/module/sw-extension/service/extension-error-handler.service`
