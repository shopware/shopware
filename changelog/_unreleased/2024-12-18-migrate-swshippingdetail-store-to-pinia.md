---
title: Migrate swShippingDetail store to Pinia
issue: NEXT-39907
author: Iván Tajes Vidal
author_email: tajespasarela@gmail.com
author_github: @Iván Tajes Vidal
---
# Administration
* Removed the `swShippingDetail` store written in Vuex (replaced with a Pinia store)
* Added a new `swShippingDetail` store written in Pinia
___
# Upgrade Information
## "swShippingDetail" Vuex store moved to Pinia

The `swShippingDetail` store has been migrated from Vuex to Pinia. The store is now available as a Pinia store and can be accessed via `Shopware.Store.get('swShippingDetail')`.

### Before:
```js
Shopware.State.get('swShippingDetail');
```

### After:
```js
Shopware.Store.get('swShippingDetail');
```

## Removed `setShippingMethod` mutation from `swShippingDetail` store

The `setShippingMethod` mutation has been removed from the `swShippingDetail` store. Instead, you can now directly mutate the `shippingMethod` state.

### Before:
```js
Shopware.State.get('swShippingDetail').setShippingMethod(shippingMethod);
```

### After:
```js
Shopware.Store.get('swShippingDetail').shippingMethod = shippingMethod;
```

## Removed `setCurrencies` mutation from `swShippingDetail` store

The `setCurrencies` mutation has been removed from the `swShippingDetail` store. Instead, you can now directly mutate the `currencies` state.

### Before:
```js
Shopware.State.get('swShippingDetail').setCurrencies(currencies);
```

### After:
```js
Shopware.Store.get('swShippingDetail').currencies = currencies;
```

## Removed `setRestrictedRuleIds` mutation from `swShippingDetail` store

The `setRestrictedRuleIds` mutation has been removed from the `swShippingDetail` store. Instead, you can now directly mutate the `restrictedRuleIds` state.

### Before:
```js
Shopware.State.get('swShippingDetail').setRestrictedRuleIds(restrictedRuleIds);
```

### After:
```js
Shopware.Store.get('swShippingDetail').restrictedRuleIds = restrictedRuleIds;
```
