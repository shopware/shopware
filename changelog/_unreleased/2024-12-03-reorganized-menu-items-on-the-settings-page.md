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
* Changed `sw-settings-index.html.twig` to implement the new groups and reflect the updated menu structure.

___

# Upgrade Information
## Updated Menu Structure in Settings Page
The settings page has been reorganized into groups for better usability. If you extend or customize the settings menu, ensure that your changes are compatible with the new structure.

### Details:
* Changed settings-item.store.js to extend the state for supporting the reorganized menu structure on the settings page.
* Changed sw-settings-index.html.twig to implement the new groups and reflect the updated menu structure.

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

Additionally, the `addItem` function has been updated to allow dynamic addition of items to the appropriate group:
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
The Twig Template file has also been updated:
```html
<div v-for="(settingsItems, settingsGroup) in settingsGroups"
     :key="settingsGroup"
     class="sw-settings__content-group"
>
    <h2>{{ getGroupLabel(settingsGroup) }}</h2>

    <sw-settings-item
        v-for="settingsItem in settingsItems"
        :id="settingsItem.id"
        :key="settingsItem.name"
        :label="getLabel(settingsItem)"
        :to="getRouteConfig(settingsItem)"
        :background-enabled="settingsItem.backgroundEnabled"
    >
        <template #icon>
            <component
                :is="settingsItem.iconComponent"
                v-if="settingsItem.iconComponent"
            />
            <sw-icon
                v-else
                :name="settingsItem.icon"
            />
        </template>
    </sw-settings-item>
</div>
```

## Required Adjustments for Custom Plugins
If your plugin extends `sw-settings-index.html.twig`, you must update the overridden templates to align with the new menu group structure. Add support for groups by wrapping items within the required group tags.

# Next Major Version Changes
## Deprecation of Legacy Menu Structure
The old menu structure in the settings page is deprecated and will be removed in `v6.7.0.0`.
