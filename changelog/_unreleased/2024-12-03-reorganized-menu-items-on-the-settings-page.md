---
title: Reorganized menu items on the settings page
issue: NEXT-37079
flag: v6.7.0.0
author: Sebastian Kalicki
author_email: s.kalicki@shopware.com
author_github: @s.kalicki
---
# Administration
* Changed `settings-item.store.js` to extend the state for supporting the reorganized menu structure on the settings page.
* Changed `sw-settings-document-detail.html.twig` to implement the new groups and reflect the updated menu structure.

# Upgrade Information
## Updated Menu Structure in Settings Page
The settings page has been reorganized into groups for better usability. If you extend or customize the settings menu, ensure that your changes are compatible with the new structure.

### Details:
* Changed settings-item.store.js to extend the state for supporting the reorganized menu structure on the settings page.
* Changed sw-settings-document-detail.html.twig to implement the new groups and reflect the updated menu structure.

### Code Updates:
In `settings-item.store.js`, the state has been extended with the following `settingsGroups` object:
```javascript
settingsGroups: {
    general: [],
    customer: [],
    automation: [],
    localization: [],
    content: [],
    commerce: [],
    system: [],
    plugins: [],
    shop: [],
},
```

Additionally, the `addItem` function has been implemented to allow dynamic addition of items to the appropriate group:
```javascript
addItem(state, { group, item }) {
    let group = settingsItem.group;

    if (typeof group === 'function') {
        group = group();
    }

    if (!group || typeof group !== 'string') {
        throw new Error('Group is undefined or invalid');
    }
    // ...
}
```

## Required Adjustments for Custom Plugins
If your plugin extends `sw-settings-document-detail.html.twig`, you must update the overridden templates to align with the new menu group structure. Add support for groups by wrapping items within the required group tags.

# Next Major Version Changes
## Deprecation of Legacy Menu Structure
The old flat menu structure in the settings page is deprecated and will be removed in `v6.7.0.0`. Plugins relying on the flat menu structure must update to use the new grouped menu format.

