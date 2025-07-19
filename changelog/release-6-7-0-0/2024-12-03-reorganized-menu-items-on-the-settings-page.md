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
* Deprecated blocks in `sw-settings-index.html.twig
  `:`sw_settings_content_tab_shop`, `sw_settings_content_tab_system`, `sw_settings_content_tab_plugins`, `sw_settings_content_card`, `sw_settings_content_header`, `sw_settings_content_card_content`
* Added new blocks in `sw-settings-index.html.twig`: `sw_settings_content_card_content_grid`, `sw_settings_content_card_view`, `sw_settings_content_card_view_header`
___

# Upgrade Information
## Updated Menu Structure in Settings Page
The settings page has been changed from tab structure into a grid structure. New grouping has been added for better usability. If you extend or customize the settings menu, ensure that your changes are compatible with the new structure.

The current menu structure is as follows:
* General
* Customer
* Automation
* Localization
* Content
* Commerce
* System
* Account
* Extensions

To add a new menu item for your module you can define which group it should belong to like this:
```
Module.register('sw-settings-custom', {
    ...
    settingsItem: {
        group: 'general',
        ...    
    },
});
```

Available groups are: general, customer, automation, localization, content, commerce, system, account, plugins.

### Required Adjustments for Custom Plugins
If your plugin extends `sw-settings-index.html.twig`, you must update the overridden templates to align with the new menu group structure.

# Next Major Version Changes
## Settings Menu Structure was changed 
The menu structure on the settings page has changed from tab structure to a grid structure. The new structure groups settings into different categories for better usability. If you extend or customize the settings menu, ensure that your changes are compatible with the new structure.

The new settings groups are:
* General
* Customer
* Automation
* Localization
* Content
* Commerce
* System
* Account
* Extensions

As a result blocks have been removed in `sw-settings-index.html.twig`:
* `sw_settings_content_tab_shop`
* `sw_settings_content_tab_system`
* `sw_settings_content_tab_plugins`
* `sw_settings_content_card`
* `sw_settings_content_header`
* `sw_settings_content_card_content`

New blocks have been added in `sw-settings-index.html.twig`:
* `sw_settings_content_card_content_grid`
* `sw_settings_content_card_view`
* `sw_settings_content_card_view_header`

