---
title: Release app system
issue: NEXT-10286
---
# Administration
* Changed key attribute for `sw-admin-menu-items` to react to changes in children.
* Removed calls to `AppActionButtonService.getActionButtonsPerView` if `entity` and `view` are not set for current route.
___
# Core
* Released the app system, therefore removed the FEATURE_NEXT_102866 flag.
