---
title: Migrate swOrderDetail store to Pinia
issue: NEXT-39904
author: Iván Tajes Vidal
author_email: i.tajesvidal@shopware.com
author_github: @Iván Tajes Vidal
---
# Administration
* Removed the `swOrderDetail` store written in Vuex (replaced with a Pinia store)
* Added a new `swOrderDetail` store written in Pinia
___
# Upgrade Information
## "swOrderDetail" Vuex store moved to Pinia

The `swOrderDetail` store has been migrated from Vuex to Pinia. The store is now available as a Pinia store and can be accessed via `Shopware.Store.get('swOrderDetail')`.

### Before:
```js
Shopware.State.get('swOrderDetail');
```

### After:
```js
Shopware.Store.get('swOrderDetail');
```

## Removed `setOrder` mutation from `swOrderDetail` store

The `setOrder` mutation has been removed from the `swOrderDetail` store. Instead, you can now directly mutate the `order` state.

### Before:
```js
Shopware.State.get('swOrderDetail').setOrder(newOrder);
```

### After:
```js
Shopware.Store.get('swOrderDetail').order = newOrder;
```

## Removed `setEditing` mutation from `swOrderDetail` store

The `setEditing` mutation has been removed from the `swOrderDetail` store. Instead, you can now directly mutate the `editing` state.

### Before:
```js
Shopware.State.get('swOrderDetail').setEditing(value);
```

### After:
```js
Shopware.Store.get('swOrderDetail').editing = value;
```

## Removed `setSavedSuccessful` mutation from `swOrderDetail` store

The `setSavedSuccessful` mutation has been removed from the `swOrderDetail` store. Instead, you can now directly mutate the `savedSuccessful` state.

### Before:
```js
Shopware.State.get('swOrderDetail').setSavedSuccessful(value);
```

### After:
```js
Shopware.Store.get('swOrderDetail').savedSuccessful = value;
```

## Removed `setVersionContext` mutation from `swOrderDetail` store

The `setVersionContext` mutation has been removed from the `swOrderDetail` store. Instead, you can now directly mutate the `versionContext` state.

### Before:
```js
Shopware.State.get('swOrderDetail').setVersionContext(versionContext);
```

### After:
```js
Shopware.Store.get('swOrderDetail').versionContext = versionContext;
```
