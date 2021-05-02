---
title: Fix error saving category canonical url non-default language
issue: NEXT-14837
---
# Administration
* Change method `refreshCurrentSeoUrl` in `src/module/sw-settings-seo/component/sw-seo-url/index.js` to set entity by current saleChannel when defaultSeoUrl is empty.
