---
title: Migrate session store to Pinia
issue: NEXT-38632
author: Iván Tajes Vidal
author_email: tajespasarela@gmail.com
author_github: @Iván Tajes Vidal
---
# Administration
* Removed the `session` store written in Vuex (replaced with a Pinia store)
* Added a new `session` store written in Pinia
___
# Upgrade Information

## "session" Vuex store moved to Pinia

The `session` store has been migrated from Vuex to Pinia. The store is now available as a Pinia store and can be accessed via `Shopware.Store.get('session')`.

### Before:
```js
Shopware.State.get('session');
```

### After:
```js
Shopware.Store.get('session');
```
