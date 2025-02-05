---
title: Replace "modals" Vuex store with Pinia store
issue: NEXT-39387
author: Iván Tajes Vidal
author_email: tajespasarela@gmail.com
author_github: @Iván Tajes Vidal
---
# Administration
* Removed the `modals` store written in Vuex (replaced with a Pinia store)
* Added a new `modals` store written in Pinia
___
# Upgrade Information
## "modals" Vuex store moved to Pinia

The `modals` store has been migrated from Vuex to Pinia. The store is now available as a Pinia store and can be accessed via `Shopware.Store.get('modals')`.

### Before:
```js
Shopware.State.get('modals');

Shopware.State.commit('modals/openModal', modalEntry);
Shopware.State.commit('modals/closeModal', locationId);
```

### After:
```js
Shopware.Store.get('modals');

Shopware.Store.get('modals').openModal(modalEntry);
Shopware.Store.get('modals').closeModal(locationId);
```
