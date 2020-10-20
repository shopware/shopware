---
title: Add Administration UI for app system
issue: NEXT-10286
---
# Core

*  Changed catalouge to category for available entities for app actions
___
# Administration
* Added component `sw-app-action-button`
* Added component `sw-app-actions`
* Added component `sw-app-app-url-changed-modal`
* Added module `sw-my-apps`
* Added state module `shopwareApps` to store information of installed apps
* Added appActionButtonService
* Added appModulesService
* Added appUrlChangeService
* Added appSystem meta information to sw.product.index route
* Added appSystem meta information to sw.product.detail route
* Added appSystem meta information to sw.category.index route
* Added appSystem meta information to sw.order.index route
* Added appSystem meta information to sw.order.detail route
* Added appSystem meta information to sw.promotion.index route
* Added appSystem meta information to sw.promotion.detail route
* Added flush-promises to dev-dependencies
* Replaced `orderId` field through prop in `sw-order-detail`
* Removed focus() in `sw-modal` in `beforeDestroyed` hook
