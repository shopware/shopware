---
title: Migrate Admin Menu Store to Pinia
issue: NEXT-38617
author: Iván Tajes Vidal
author_email: tajespasarela@gmail.com
author_github: @Iván Tajes Vidal
---
# Administration
* Removed the `adminMenu` store written in Vuex (replaced with a Pinia store)
* Added a new `adminMenu` store written in Pinia
___
# Upgrade Information
## "adminMenu" Vuex store moved to Pinia

The `adminMenu` store has been migrated from Vuex to Pinia. The store is now available as a Pinia store and can be accessed via `Shopware.Store.get('adminMenu')`.

### Before:
```js
Shopware.State.get('adminMenu');
```

### After:
```js
Shopware.Store.get('adminMenu');
```

