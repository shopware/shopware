---
title: Migrate swOrder store to Pinia
issue: NEXT-40012
author: Iván Tajes Vidal
author_email: i.tajesvidal@shopware.com
author_github: @Iván Tajes Vidal
---
# Administration
* Removed the `swOrder` store written in Vuex (replaced with a Pinia store)
* Added a new `swOrder` store written in Pinia
___
# Upgrade Information
## "swOrder" Vuex store moved to Pinia

The `swOrder` store has been migrated from Vuex to Pinia. The store is now available as a Pinia store and can be accessed via `Shopware.Store.get('swOrder')`.

### Before:
```js
Shopware.State.get('swOrder');
```

### After:
```js
Shopware.Store.get('swOrder');
```
