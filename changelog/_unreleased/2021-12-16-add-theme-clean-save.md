---
title: Added new save action for theme administration
issue: NEXT-114571
---
# Administration
* Added new blocks to `Storefront/Resources/app/administration/src/modules/sw-theme-manager/page/sw-theme-manager-detail/sw-theme-manager-detail.html.twig` with additional save action:
  * `sw_theme_manager_detail_actions_save_context_menu`
  * `sw_theme_manager_detail_actions_save_context_menu_actions`
  * `sw_theme_manager_detail_actions_save_clean`
* Changed methods in `Storefront/Resources/app/administration/src/modules/sw-theme-manager/page/sw-theme-manager-detail/index.js` to check for clean save action by adding parameter `clean`:
  * `onSaveTheme`
  * `getCurrentChangeset`
  * `saveThemeConfig`
* Added new method `onSaveClean` in `Storefront/Resources/app/administration/src/modules/sw-theme-manager/page/sw-theme-manager-detail/index.js`.
* Added snippets `sw-theme-manager.actions.saveClean` and `sw-theme-manager.actions.saveCleanToolTip`. 