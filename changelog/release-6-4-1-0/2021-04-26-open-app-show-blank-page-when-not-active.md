---
title: Open app show blank page when not active
issue: NEXT-14936
---
# Administration
* Deprecated `span` element in `src/Administration/Resources/app/administration/src/module/sw-extension/component/sw-extension-card-base/sw-extension-card-base.html.twig`
* Added block `sw_extension_card_base_context_menu_theme_settings` in `src/Administration/Resources/app/administration/src/module/sw-extension/component/sw-extension-card-base/sw-extension-card-base.html.twig`
* Changed method `canBeOpened` of `ShopwareExtensionService` will now return false when the extension is a theme and has not been activated once.
