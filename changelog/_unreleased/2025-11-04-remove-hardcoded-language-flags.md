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
# Next Major Version Changes

## Removal of hardcoded language flags:

* Deprecated hardcoded CSS language flags in `src/Storefront/Resources/app/storefront/src/scss/component/_flags.scss` - file will be removed in v6.8.0.0
* Added extensible Twig block `layout_header_actions_language_widget_content_inner` and `layout_header_actions_languages_widget_form_items_flag_inner` allowing plugins/themes to add custom flag implementations
