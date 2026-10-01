---
title: Migrate showpwareExtensions store to Pinia
issue: NEXT-39903
author: Iván Tajes Vidal
author_email: i.tajesvidal@shopware.com
author_github: @Iván Tajes Vidal
---
# Administration
* Removed the `shopwareExtensions` store written in Vuex (replaced with a Pinia store)
* Added a new `shopwareExtensions` store written in Pinia
___
# Upgrade Information
## "shopwareExtensions" Vuex store moved to Pinia

The `shopwareExtensions` store has been migrated from Vuex to Pinia. The store is now available as a Pinia store and can be accessed via `Shopware.Store.get('shopwareExtensions')`.

### Before:
```js
Shopware.State.get('shopwareExtensions');
```

### After:
```js
Shopware.Store.get('shopwareExtensions');
```

## Removed `setExtensionListing` mutation from `shopwareExtensions` store

The `setExtensionListing` mutation has been removed from the `shopwareExtensions` store. Instead, you can now directly mutate the `extensionListing` state.

### Before:
```js
Shopware.State.get('shopwareExtensions').setExtensionListing(extensions);
```

### After:
```js
Shopware.Store.get('shopwareExtensions').extensionListing = extensions;
```

## Removed `categoriesLanguageId` mutation from `shopwareExtensions` store

The `categoriesLanguageId` mutation has been removed from the `shopwareExtensions` store. Instead, you can now directly mutate the `categoriesLanguageId` state.

### Before:
```js
Shopware.State.get('shopwareExtensions').categoriesLanguageId(languageId);
```

### After:
```js
Shopware.Store.get('shopwareExtensions').categoriesLanguageId = languageId;
```

## Removed `setUserInfo` mutation from `shopwareExtensions` store

The `setUserInfo` mutation has been removed from the `shopwareExtensions` store. Instead, you can now directly mutate the `userInfo` state.

### Before:
```js
Shopware.State.get('shopwareExtensions').setUserInfo(userInfo);
```

### After:
```js
Shopware.Store.get('shopwareExtensions').userInfo = userInfo;
```

## Removed `myExtensions` mutation from `shopwareExtensions` store

The `myExtensions` mutation has been removed from the `shopwareExtensions` store. Instead, you have to use `setMyExtensions` action. This change is done to avoid conflicts with the state `myExtesions`.

### Before:
```js
Shopware.State.get('shopwareExtensions').myExtensions(extensions);
```

### After:
```js
Shopware.State.get('shopwareExtensions').setMyExtensions(extensions);
```
