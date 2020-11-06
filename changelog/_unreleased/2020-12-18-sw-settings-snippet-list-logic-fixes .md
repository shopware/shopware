---
title: sw-settings-snippet-list logic fixes
issue: NEXT-9533
author: NiklasLimberg
author_email: n.limberg@shopware.com 
author_github: NiklasLimberg
---
# Core
* Removed variable `this.selectionCount` because it dosn't exist in the data object, instead `this.hasResetableItems` is calculated directly in the `onSelectionChanged` method in `/module/sw-settings-snippet/page/sw-settings-snippet-list/index.js`
* Changed the `onSortColumn` method to simplify it in `/module/sw-settings-snippet/page/sw-settings-snippet-list/index.js`
* Changed the `onPageChange` method to simplify it in `/module/sw-settings-snippet/page/sw-settings-snippet-list/index.js`
