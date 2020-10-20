---
title: fix double click onto add integration button
issue: NEXT-10935
---
# Administration
*  Changed `onGenerateKeys` method of `module/sw-integration/page/sw-integration-list/index.js` to assigned new value for `currentIntegration` if it is null.
