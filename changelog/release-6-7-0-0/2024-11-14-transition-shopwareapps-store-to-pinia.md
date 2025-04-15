---
title: Transition shopwareApps store to pinia
issue: NEXT-38637
author: Jannis Leifeld
author_email: j.leifeld@shopware.com
author_github: @Jannis Leifeld
---

# Administration
* Removed the `shopwareApps` store written in Vuex (replaced with a Pinia store)
* Added a new `shopwareApps` store written in Pinia
___
# Upgrade Information
## "shopwareApps" Vuex store moved to Pinia

The `shopwareApps` store has been migrated from Vuex to Pinia. The store is now available as a Pinia store and can be accessed via `Shopware.Store.get('shopwareApps')`.

### Before:
```js
Shopware.State.get('shopwareApps');
```

### After:
```js
Shopware.Store.get('shopwareApps');
```

## Removed `setApps` mutation from `shopwareApps` store

The `setApps` mutation has been removed from the `shopwareApps` store. Instead, you can now directly mutate the `shopwareApps` state.

### Before:
```js
Shopware.State.get('shopwareApps').setApps([ ...theApps ]);
```

### After:
```js
Shopware.Store.get('shopwareApps').setApps = [ ...theApps ];
```

## Removed `setSelectedIds` mutation from `shopwareApps` store

The `setSelectedIds` mutation has been removed from the `shopwareApps` store. Instead, you can now directly mutate the `shopwareApps` state.

### Before:
```js
Shopware.State.get('shopwareApps').setSelectedIds([ ...theIds ]);
```

### After:
```js
Shopware.Store.get('shopwareApps').setSelectedIds = [ ...theIds ];
```
