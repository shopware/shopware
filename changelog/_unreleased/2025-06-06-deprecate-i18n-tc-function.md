---
title: deprecate i18n $tc function
issue: 9931
author: Iván Tajes Vidal
author_email: tajespasarela@gmail.com
author_github: @Iván Tajes Vidal
---
# Administration
* Added deprecation warning for the `$tc` function in favor of `$t` for Vue components
* Added feature flag check `V6_8_0_0` to display deprecation warning for `$tc` function
* Added workaround to handle parameter order changes between Vue i18n versions
* Changed `vue.adapter.ts` to use the new i18n options with proper warning suppression
___
# Upgrade Information
## Vue i18n Translation Functions
* The `$tc` function is deprecated and will be removed in v6.8.0
* Use `$t` function instead for all translations
* The `$tc` function now shows a deprecation warning when used with the feature flag `V6_8_0_0` enabled
___
# Next Major Version Changes
## Removal of $tc function:
* The `$tc` function will be completely removed
* All translation calls should use `$t` instead
