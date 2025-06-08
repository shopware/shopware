---
title: remove deprecated vue2 option api usage
issue: NEXT-33867
author: Lars Kemper
author_email: l.kemper@shopware.com
author_github: @LarsKemper
---
# Administration
* Changed `modalNameFromLogEntry()` computed in `src/module/sw-settings-logging/page/sw-settings-logging-list/index.js` to remove deprecated vue2 option api usage. Using the Component registry instead.
