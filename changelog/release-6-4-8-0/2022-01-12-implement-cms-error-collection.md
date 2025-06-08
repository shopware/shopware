---
title: implement cms error collection
issue: NEXT-19224
---
# Administration
* Added collection and display of errors to `sw-cms-detail` and `sw-cms-sidebar`
* Added properties `badgeType` and `hasSimpleBadge` to `sw-sidebar-item` to be used by `sw-sidebar-navigation-item`, which renders those as notification badges in the 4 common types
* Deprecated data properties `missingElements` and `isSaveable` in `sw-cms-detail`
* Deprecated methods `getRedundantElementsWarning` and `getMissingElements` in `sw-cms-detail`