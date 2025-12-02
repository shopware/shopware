---
title: Migrate paymentOverviewCard store to Pinia
issue: NEXT-40102
author: Iván Tajes Vidal
author_email: tajespasarela@gmail.com
author_github: @Iván Tajes Vidal
---
# Administration
* Removed the `paymentOverviewCard` store written in Vuex (replaced with a Pinia store)
* Added a new `paymentOverviewCard` store written in Pinia
___
# Upgrade Information
## "paymentOverviewCard" Vuex store moved to Pinia

The `paymentOverviewCard` store has been migrated from Vuex to Pinia. The store is now available as a Pinia store and can be accessed via `Shopware.Store.get('paymentOverviewCard')`.

### Before:
```js
Shopware.State.get('paymentOverviewCard');
```

### After:
```js
Shopware.Store.get('paymentOverviewCard');
```
