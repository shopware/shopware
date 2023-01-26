---
title: Remove app navigation entries app name prefix
issue: NEXT-20741
author: Fabian Hüske
author_email: f.hueske@shopware.com
---
# Administration
* Changed `getNavigationFromApp()` in `src/app/service/menu.service.js` to only use `moduleLabel` instead of `appLabel - moduleLabel` to generate the navigation entry label.
___
# Upgrade Information
## Removed prefix from app module menu entries
As for now we have prefixed your app's module label with the app name to build navigation entries.
From 6.5 on, this prefixing will be removed.

```diff
const entry = {
    id: `app-${app.name}-${appModule.name}`,
    label: {
        translated: true,
-       label: `${appLabel} - ${moduleLabel}`,
+       label: moduleLabel,
    },
    position: appModule.position,
    parent: appModule.parent,
    privilege: `app.${app.name}`,
};
```
**Example:** `Your App - Module Label` will become `Module Label` in Shopware's Administration menu.

**Important:** Please update your module label in your app's `manifest.xml` so it's clearly identifiable by your users.
Keep in mind that using a generic label could lead to cases where multiple apps use the same or similar module labels.
