---
title: Show warning modal about unsaved data on leaving
issue: NEXT-14623
---
# Administration
*  Added methods `beforeRouteUpdate`,`beforeRouteLeave`, `unsavedDataLeaveHandler`, `onEditChanged`, `onConfirmLeave`,`onCloseLeaveModal`, `onCancelLeaveModal` to show the warning modal on leaving unsaved data in `src/module/sw-settings-search/page/sw-settings-search/index.js`
* Added block `sw_settings_search_discard_model` for the warning popup about the unsaved data in `src/module/sw-settings-search/page/sw-settings-search/sw-settings-search.html.twig`.
