---
title: migrate swFlow store to Pinia
issue: NEXT-40368
author: Iván Tajes Vidal
author_email: tajespasarela@gmail.com
author_github: @Iván Tajes Vidal
---
# Administration
* Removed the `swFlow` store written in Vuex (replaced with a Pinia store)
* Added a new `swFlow` store written in Pinia
___
# Upgrade Information
## "swFlow" Vuex store moved to Pinia

The swFlow store has been migrated from Vuex to Pinia. The store is now available as a Pinia store and can be accessed via `Shopware.Store.get('swFlow')`.

### Before:
```js
Shopware.State.get('swFlow');
```

### After:
```js
Shopware.Store.get('swFlow');
``` 

## Removed `setTriggerActions` getter from `swFlow` store

The `setTriggerActions` mutation has been removed from the `swFlow` store. Instead, you can now directly modify the `triggerActions` state.

### Before:
```js
Shopware.State.dispatch('swFlow/setTriggerActions', actions);
```

### After:
```js
Shopware.Store.get('swFlow').triggerActions = actions;
```

## Removed `setTriggerEvent` getter from `swFlow` store

The `setTriggerEvent` mutation has been removed from the `swFlow` store. Instead, you can now directly modify the `triggerEvent` state.

### Before:
```js
Shopware.State.dispatch('swFlow/setTriggerEvent', event);
```

### After:
```js
Shopware.Store.get('swFlow').triggerEvent = event;
```

## Removed `setTriggerEvents` getter from `swFlow` store

The `setTriggerEvents` mutation has been removed from the `swFlow` store. Instead, you can now directly modify the `triggerEvents` state.

### Before:
```js
Shopware.State.dispatch('swFlow/setTriggerEvents', events);
```

### After:
```js
Shopware.Store.get('swFlow').triggerEvents = events;
```

## Removed `setStateMachineState` getter from `swFlow` store

The `setStateMachineState` mutation has been removed from the `swFlow` store. Instead, you can now directly modify the `stateMachineState` state.

### Before:
```js
Shopware.State.dispatch('swFlow/setStateMachineState', state);
```

### After:
```js
Shopware.Store.get('swFlow').stateMachineState = state;
```

## Removed `setInvalidSequences` getter from `swFlow` store

The `setInvalidSequences` mutation has been removed from the `swFlow` store. Instead, you can now directly modify the `invalidSequences` state.

### Before:
```js
Shopware.State.dispatch('swFlow/setInvalidSequences', invalidSequences);
```

### After:
```js
Shopware.Store.get('swFlow').invalidSequences = invalidSequences;
```

## Removed `setDocumentTypes` getter from `swFlow` store

The `setDocumentTypes` mutation has been removed from the `swFlow` store. Instead, you can now directly modify the `documentTypes` state.

### Before:
```js
Shopware.State.dispatch('swFlow/setDocumentTypes', documentTypes);
```

### After:
```js
Shopware.Store.get('swFlow').documentTypes = documentTypes;
```

## Removed `setCustomerGroups` getter from `swFlow` store

The `setCustomerGroups` mutation has been removed from the `swFlow` store. Instead, you can now directly modify the `customerGroups` state.

### Before:
```js
Shopware.State.dispatch('swFlow/setCustomerGroups', customerGroups);
```

### After:
```js
Shopware.Store.get('swFlow').customerGroups = customerGroups;
```

## Removed `setMailTemplates` getter from `swFlow` store

The `setMailTemplates` mutation has been removed from the `swFlow` store. Instead, you can now directly modify the `mailTemplates` state.

### Before:
```js
Shopware.State.dispatch('swFlow/setMailTemplates', mailTemplates);
```

### After:
```js
Shopware.Store.get('swFlow').mailTemplates = mailTemplates;
```

## Removed `setCustomFieldSets` getter from `swFlow` store

The `setCustomFieldSets` mutation has been removed from the `swFlow` store. Instead, you can now directly modify the `customFieldSets` state.

### Before:
```js
Shopware.State.dispatch('swFlow/setCustomFieldSets', customFieldSets);
```

### After:
```js
Shopware.Store.get('swFlow').customFieldSets = customFieldSets;
```

## Removed `setCustomFields` getter from `swFlow` store

The `setCustomFields` mutation has been removed from the `swFlow` store. Instead, you can now directly modify the `customFields` state.

### Before:
```js
Shopware.State.dispatch('swFlow/setCustomFields', customFields);
```

### After:
```js
Shopware.Store.get('swFlow').customFields = customFields;
```

## Removed `setRestrictedRules` getter from `swFlow` store

The `setRestrictedRules` mutation has been removed from the `swFlow` store. Instead, you can now directly modify the `restrictedRules` state.

### Before:
```js
Shopware.State.dispatch('swFlow/setRestrictedRules', restrictedRules);
```

### After:
```js
Shopware.Store.get('swFlow').restrictedRules = restrictedRules;
```


