---
title: Make settings module compatible with Vue3
issue: NEXT-29011
author: Jannis Leifeld
author_email: j.leifeld@shopware.com
author_github: Jannis Leifeld
---
# Administration
* Changed emit value of `sw-media-field` for the `mediaId` to `update:value` instead of `input` to be compatible with Vue3
* Changed keys in `sw-medie-folder-item` to make them unique for Vue3
* Removed the compatibility layer `INSTANCE_ATTRS_CLASS_STYLE` in `sw-card` to fix the styling issue when custom classes are used
* Changed the keys to the `template` v-for in `sw-custom-field-set-renderer`
* Changed parent call behavior in `sw-search-bar`
* Added `&nbsp;` (whitespace character) to the shortcuts snippet for the plural values because empty values aren't valid in Vue3 anymore
* Removed manual autocompletion assignment with `this.$refs` in `sw-mail-template-detail` because it is not needed because it will be provided also with the properties
* Added early return to `addressFormatSettingsLink` in `sw-settings-address` when no `defaultContry` is set and update the `v-show` value to check if the value is set
* Added optional chaining to `checkEmptyState` in `sw-settings-country-state`
* Changed the second `$tc` parameter from boolean to number because it is not a boolean value
* Changed the v-model behavior inside `sw-settings-document-detail` and changed the keys to the `template` v-for to be compatible with Vue3
* Changed the `key` in `sw-settings-language-detail` to a unique key to be compatible with Vue3
* Removed unnecessary assignment of prop in `sw-settings-search-searchable-content`
* Changed the keys in `sw-users-permissions-additional-permissions` to make them unique for Vue3
* Changed the keys in `sw-users-permissions-detailed-additional-permissions` to make them unique for Vue3 
