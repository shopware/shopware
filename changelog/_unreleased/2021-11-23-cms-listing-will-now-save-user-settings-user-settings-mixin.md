---
title: CMS listing will now save user settings + user settings mixin
issue: NEXT-10149
---
# Administration
* Added `user-settings.mixin.js` to have an easier way to access user settings for custom use, providing the following methods:
  * `getUserSettingsEntity`, which provides the whole user settings entity
  * `getUserSettings`, which provides just the values of the user settings entity
  * `saveUserSettings`, saves the user settings values
* Added saving of user settings (grid mode, column display, sorting) for the `sw-cms-list`
