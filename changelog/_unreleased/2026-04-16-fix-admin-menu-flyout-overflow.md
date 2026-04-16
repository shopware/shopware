---
title: Fix admin menu flyout overflowing viewport
issue: #16219
author_github: @zaifastafa
---
# Administration
* Changed `sw-admin-menu` flyout to calculate a dynamic `max-height` based on remaining viewport space, preventing the submenu from extending beyond the visible area.
* Changed `sw-admin-menu` flyout to enable vertical scrolling via `overflow-y: auto` when content exceeds the available height.
