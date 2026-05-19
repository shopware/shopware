---
title: Replace "menuItem" Vuex store with Pinia store
issue: NEXT-38630
author: Iván Tajes Vidal
author_email: tajespasarela@gmail.com
author_github: @Iván Tajes Vidal
---
# Administration
* Removed the `menuItem` store written in Vuex (replaced with a Pinia store)
* Added a new `menuItem` store written in Pinia
___
# Upgrade Information
## "menuItem" Vuex store moved to Pinia

The `menuItem` store has been migrated from Vuex to Pinia. The store is now available as a Pinia store and can be accessed via `Shopware.Store.get('menuItem')`.

### Before:
```js
Shopware.State.get('menuItem');

Shopware.State.commit('menuItem/addMenuItem', menuItem);
```

### After:
```js
Shopware.Store.get('menuItem');

Shopware.Store.get('menuItem').addMenuItem(menuItem);
```
