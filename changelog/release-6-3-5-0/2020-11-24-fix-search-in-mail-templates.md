---
title: Fix search in mail templates
issue: NEXT-7072
---
# Administration
* Added prop `searchTerm` to `sw-mail-header-footer-list`
* Added computed property `skeletonItemAmount` and `showListing` to `sw-mail-header-footer-list`
* Added method `updateRecords` to `sw-mail-header-footer-list`
* Added prop `searchTerm` to `sw-mail-temlate-list`
* Added computed property `skeletonItemAmount` and `showListing` to `sw-mail-temlate-list`
* Added method `updateRecords` to `sw-mail-temlate-list`
* Added scss file for `sw-mail-template-index` component
* Added following snippets:
    * `sw-mail-header-footer.list.emptyStateTitle`
    * `sw-mail-header-footer.list.emptyStateSubTitle`,
    * `sw-mail-template.list.emptyStateTitle`
    * `sw-mail-template.list.emptyStateSubTitle`
* Removed following snippets:
    * `sw-mail-header-footer.list.messageEmpty`
    * `sw-mail-template.list.messageEmpty`,
