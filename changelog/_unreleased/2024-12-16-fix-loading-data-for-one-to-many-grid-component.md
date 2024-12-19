---
title: Fix loading data for one to many grid component
issue: NEXT-00000
author: Le Nguyen
author_email: l.nguyen@shopware.com
author_github: @nguyenquocdaile
---
# Administration
* Added `watch` property to watching the data changed of props collection in the `sw-one-to-many-grid` component.
* Changed `deleteItems` method to fix loading data when delete all items of last page in the `sw-one-to-many-grid` component.
* Deprecated `intial` data to fix typo use `initial` instead in the `sw-one-to-many-grid` component.
* Changed `applyResult` method to check correct condition when apply result in the `sw-one-to-many-grid` component.
