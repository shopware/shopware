---
title: Migrate rule-conditions-config store to pinia
issue: NEXT-38633
author: Jannis Leifeld
author_email: j.leifeld@shopware.com
author_github: @Jannis Leifeld
---
# Administration
* Removed the `ruleConditionsConfig` store written in Vuex (replaced with a Pinia store)
* Added a new `ruleConditionsConfig` store written in Pinia
___
# Upgrade Information

## "ruleConditionsConfig" Vuex store moved to Pinia

The `ruleConditionsConfig` store has been migrated from Vuex to Pinia. The store is now available as a Pinia store and can be accessed via `Shopware.Store.get('ruleConditionsConfig')`.

### Before:
```js
Shopware.State.get('ruleConditionsConfig');
```

### After:
```js
Shopware.Store.get('ruleConditionsConfig');
```

## Removed `setConfig` mutation from `ruleConditionsConfig` store

The `setConfig` mutation has been removed from the `ruleConditionsConfig` store. Set the config directly on the store instance instead.

### Before:
```js
Shopware.State.commit('ruleConditionsConfig/setConfig', config);
```

### After:
```js
Shopware.Store.get('ruleConditionsConfig').config = config;
```

## Removed `getConfig` mutation from `ruleConditionsConfig` store

The `getConfig` getter has been removed from the `ruleConditionsConfig` store. Access the config directly on the store instance instead.

### Before:
```js
Shopware.State.getters['ruleConditionsConfig/getConfig'];
```

### After:
```js
Shopware.Store.get('ruleConditionsConfig').config;
```
