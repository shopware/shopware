---
title: Show error for not supported language on sales channel in search module
issue: NEXT-14561
---
# Administration
* Changed method `searchOnStorefront()` in `/src/module/sw-settings-search/component/sw-settings-search-live-search/index.js` to show the custom error message when the language is not supported for that sales channel.
