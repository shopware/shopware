---
title: Fix snippets inaccessible & unchangeable
issue: NEXT-39387
author: Benjamin Wittwer
author_email: benjamin.wittwer@a-k-f.de
author_github: akf-bw
---
# Administration
* Changed `prepareContent` in `sw-settings-snippet-detail/index.js` to set `isLoadingSnippets`
* Changed `onSave` in `sw-settings-snippet-detail/index.js` to correctly reset the snippet data
* Changed `sw-settings-snippet-detail/sw-settings-snippet-detail.html.twig` to remove unnecessary loading skeletons & add `isLoadingSnippets` check to the snippet inputs to block interaction while loading
