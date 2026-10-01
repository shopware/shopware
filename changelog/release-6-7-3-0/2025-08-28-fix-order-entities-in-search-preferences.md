---
title: fix order entities in search preferences
issue: #12128
---
# Administration
* Changed `processSearchPreferences` method in `search-preferences.service.js` module to provide a secondary sort by `entityName` for stability
