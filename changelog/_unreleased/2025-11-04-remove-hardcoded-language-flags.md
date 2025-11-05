---
title: Remove hardcoded language flags from storefront
issue: 4750
flag: v6.8.0.0
author: Björn Meyer
author_email: b.meyer@shopware.com
author_github: @BrocksiNet
---
# Storefront

* Changed language selector in `src/Storefront/Resources/views/storefront/layout/header/actions/language-widget.html.twig` to display language name with territory in dropdown (e.g., "Deutsch (Deutschland)", "English (United Kingdom)") when `v6.8.0.0` feature flag is active
___
# Upgrade Information

## Deprecation of hardcoded language flags:

* Hardcoded CSS language flags in `src/Storefront/Resources/app/storefront/src/scss/component/_flags.scss` are deprecated and will be removed in v6.8.0.0
* Extensible Twig blocks `layout_header_actions_language_widget_content_inner` and `layout_header_actions_languages_widget_form_items_flag_inner` have been added to allow plugins/themes to add custom flag implementations
___
# Next Major Version Changes

## Removal of hardcoded language flags:

* Removed hardcoded CSS language flags from `src/Storefront/Resources/app/storefront/src/scss/component/_flags.scss`
* Added extensible Twig blocks `layout_header_actions_language_widget_content_inner` and `layout_header_actions_languages_widget_form_items_flag_inner` allowing plugins/themes to add custom flag implementations
