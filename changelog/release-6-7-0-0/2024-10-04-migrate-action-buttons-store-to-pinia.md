---
title: Migrate Action Buttons Store to Pinia
issue: NEXT-38618
author: Iván Tajes Vidal
author_email: tajespasarela@gmail.com
author_github: @Iván Tajes Vidal
---
# Administration
* Removed the `actionButtons` store written in Vuex (replaced with a Pinia store)
* Added a new `actionButtons` store written in Pinia
___
# Upgrade Information
## "actionButtons" Vuex store moved to Pinia

The `actionButtons` store has been migrated from Vuex to Pinia. The store is now available as a Pinia store and can be accessed via `Shopware.Store.get('actionButtons')`.

### Before:
```js
Shopware.State.get('actionButtons');
```

### After:
```js
Shopware.Store.get('actionButtons');
```
