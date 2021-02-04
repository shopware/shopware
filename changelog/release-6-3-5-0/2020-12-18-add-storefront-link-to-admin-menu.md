---
title: Add storefront link to sales channels in admin menu 
issue: NEXT-12955
---
# Administration
* Added a link to the storefront for storefront sales channels with a domain.
* Added new slot `sw-admin-menu-item-additional-text` to `sw-admin-menu-item` component.
* Added `salesChannelCriteria` computed property to `sw-sales-channel-menu`. Can be decorated to restrict sales channel search.
* Added `buildMenuTree` to `sw-sales-channel-menu`. Decorate it for menu item transformation.
* Deprecated `createMenuTree` in `sw-sales-channel-menu`. Decorate newly added `buildMenutree` instead.
* Deprecated `menuItems` data property in `sw-sales-channel-menu`. It will be a read only computed in future versions.
* Deprecated use of `entry.label` as additional classes for `sw-admin-menu-item` in `sw-sales-channel-menu`.
