---
title: Create help sidebar
issue: NEXT-31826
---
# Administration
* Added `sw-help-sidebar` component in `src/app/asyncComponent/sidebar`
* Added `sw-help-center` component in `src/app/asyncComponent/utils`
* Changed `modalClasses` computed property in `sw-modal` component to update the modal classes
* Deprecated `openKeyboardShortcutOverview` method in `sw-admin-menu` component
* Deprecated `sw_admin_menu_user_actions_items_keyboard_shortcuts_overview` block in `sw-admin-menu` component template
* Deprecated `sw-help-center` component in `src/app/component/utils`, use `sw-help-center-v2` instead
* Changed `onOpenShortcutOverviewModal` method in `sw-shortcut-overview` component to emit the `shortcut-open` event
* Changed `onCloseShortcutOverviewModal` method in `sw-shortcut-overview` component to emit the `shortcut-close` event
* Added `admin-help-center` store in `src/app/state`
