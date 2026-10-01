---
title: Migrate swProductDetail store to Pinia
issue: NEXT-39905
author: Iván Tajes Vidal
author_email: i.tajesvidal@shopware.com
author_github: @Iván Tajes Vidal
---
# Administration
* Removed the `swProductDetail` store written in Vuex (replaced with a Pinia store)
* Added a new `swProductDetail` store written in Pinia 
___
# Upgrade Information
## "swProductDetail" Vuex store moved to Pinia

The `swProductDetail` store has been migrated from Vuex to Pinia. The store is now available as a Pinia store and can be accessed via `Shopware.Store.get('swProductDetail')`.

### Before:
```js
Shopware.State.get('swProductDetail');
```

### After:
```js
Shopware.Store.get('swProductDetail');
```

## Removed `setApiContext` mutation from `swProductDetail` store

The `setApiContext` mutation has been removed from the `swProductDetail` store. Instead, you can now directly mutate the `apiContext` state.

### Before:
```js
Shopware.State.get('swProductDetail').setApiContext(apiContext);
```

### After:
```js
Shopware.Store.get('swProductDetail').apiContext = apiContext;
```

## Removed `setLocalMode` mutation from `swProductDetail` store

The `setLocalMode` mutation has been removed from the `swProductDetail` store. Instead, you can now directly mutate the `localMode` state.

### Before:
```js
Shopware.State.get('swProductDetail').setLocalMode(localMode);
```

### After:
```js
Shopware.Store.get('swProductDetail').localMode = localMode;
```

## Removed `setProductId` mutation from `swProductDetail` store

The `setProductId` mutation has been removed from the `swProductDetail` store. This was removed as it was not accessed or used anywhere.

## Removed `setProduct` mutation from `swProductDetail` store

The `setProduct` mutation has been removed from the `swProductDetail` store. Instead, you can now directly mutate the `product` state.

### Before:
```js
Shopware.State.get('swProductDetail').setProduct(product);
```

### After:
```js
Shopware.Store.get('swProductDetail').product = product;
```

## Removed `setVariants` mutation from `swProductDetail` store

The `setVariants` mutation has been removed from the `swProductDetail` store. Instead, you can now directly mutate the `variants` state.

### Before:
```js
Shopware.State.get('swProductDetail').setVariants(variants);
```

### After:
```js
Shopware.Store.get('swProductDetail').variants = variants;
```

## Removed `setParentProduct` mutation from `swProductDetail` store

The `setParentProduct` mutation has been removed from the `swProductDetail` store. Instead, you can now directly mutate the `parentProduct` state.

### Before:
```js
Shopware.State.get('swProductDetail').setParentProduct(parentProduct);
```

### After:
```js
Shopware.Store.get('swProductDetail').parentProduct = parentProduct;
```

## Removed `setCurrencies` mutation from `swProductDetail` store

The `setCurrencies` mutation has been removed from the `swProductDetail` store. Instead, you can now directly mutate the `currencies` state.

### Before:
```js
Shopware.State.get('swProductDetail').setCurrencies(currencies);
```

### After:
```js
Shopware.Store.get('swProductDetail').currencies = currencies;
```

## Removed `setAttributeSet` mutation from `swProductDetail` store

The `setAttributeSet` mutation has been removed from the `swProductDetail` store. Instead, you can now directly mutate the `customFieldSets` state.

### Before:
```js
Shopware.State.get('swProductDetail').setAttributeSet(newAttributeSets);
```

### After:
```js
Shopware.Store.get('swProductDetail').customFieldSets = newAttributeSets;
```

## Removed `setAdvancedModeSetting` mutation from `swProductDetail` store

The `setAdvancedModeSetting` mutation has been removed from the `swProductDetail` store. Instead, you can now directly mutate the `advancedModeSetting` state.

### Before:
```js
Shopware.State.get('swProductDetail').setAdvancedModeSetting(advancedModeSetting);
```

### After:
```js
Shopware.Store.get('swProductDetail').advancedModeSetting = advancedModeSetting;
```

## Removed `setModeSettings` mutation from `swProductDetail` store

The `setModeSettings` mutation has been removed from the `swProductDetail` store. Instead, you can now directly mutate the `modeSettings` state.

### Before:
```js
Shopware.State.get('swProductDetail').setModeSettings(advancedModeSetting);
```

### After:
```js
Shopware.Store.get('swProductDetail').modeSettings = modeSettings;
```

## Removed `setCreationStates` mutation from `swProductDetail` store

The `setCreationStates` mutation has been removed from the `swProductDetail` store. Instead, you can now directly mutate the `creationStates` state.

### Before:
```js
Shopware.State.get('swProductDetail').setCreationStates(creationStates);
```

### After:
```js
Shopware.Store.get('swProductDetail').creationStates = creationStates;
```
