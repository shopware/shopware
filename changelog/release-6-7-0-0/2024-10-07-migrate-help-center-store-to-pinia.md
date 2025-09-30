---
title: Migrate Help Center Store to Pinia
issue: NEXT-38619
author: Iván Tajes Vidal
author_email: tajespasarela@gmail.com
author_github: @Iván Tajes Vidal
---
# Administration
* Removed the `adminHelpCenter` store written in Vuex (replaced with a Pinia store)
* Added a new `adminHelpCenter` store written in Pinia
___
# Upgrade Information
## "adminHelpCenter" Vuex store moved to Pinia

The `adminHelpCenter` store has been migrated from Vuex to Pinia. The store is now available as a Pinia store and can be accessed via `Shopware.Store.get('adminHelpCenter')`.

### Before:
```js
Shopware.State.get('adminHelpCenter');
```

### After:
```js
Shopware.Store.get('adminHelpCenter');
```

## Removed `setShowHelpSidebar` mutation

The `setShowHelpSidebar` mutation has been removed from the `adminHelpCenter` store. Instead, you can now directly mutate the `showHelpSidebar` state.

### Before:
```js
Shopware.State.get('adminHelpCenter').setShowHelpSidebar(true);
```

### After:
```js
Shopware.Store.get('adminHelpCenter').showHelpSidebar = true;
```

## Removed `setShowShortcutModal` mutation

The `setShowShortcutModal` mutation has been removed from the `adminHelpCenter` store. Instead, you can now directly mutate the `showShortcutModal` state.

### Before:
```js
Shopware.State.get('adminHelpCenter').setShowShortcutModal(true);
```

### After:
```js
Shopware.Store.get('adminHelpCenter').showShortcutModal = true;
```
