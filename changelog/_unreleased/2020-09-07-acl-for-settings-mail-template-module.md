---
title:         ACL for email templates module in settings
issue:         NEXT-8950
---
# Administration
* Added ACL privileges to email templates module in settings
* Changed method `onDuplicate` in `sw-mail-header-footer-list/index.js` to navigate correctly
* Added method `checkCanBeDeleted` in `sw-mail-header-footer-list/index.js`
* Added method `getMailHeaderFooterCriteria` in `sw-mail-header-footer-list/index.js`
* Added method `showDeleteErrorNotification` in `sw-mail-header-footer-list/index.js`
* Added method `onDelete` in `sw-mail-header-footer-list/index.js` to handle deleting single mail header footer
* Added method `onMultipleDelete` in `sw-mail-header-footer-list/index.js`to handle deleting multiple mail header footers
* Added computed `allowSave` in `sw-mail-template-detail/index.js`
* Added `shortcuts` in `sw-mail-template-detail/index.js`
* Added computed `tooltipSave` to show tooltip for Save button in mail template detail page
* Added `mapPropertyErrors` in `sw-mail-template-detail/index.js` to show validation error for required fields
* Changed method `onSave` in `sw-mail-template-detail/index.js` to fix infinite loading when trying to save empty mail template
* Changed method `handleSalesChannel` in `sw-mail-tempalte-detail/index.js` to fix infinite loading when trying to save empty mail template
* Added method `allowSave` in `sw-mail-header-footer-detail/index.js`
* Added `shortcuts` in `sw-mail-header-footer-detail/index.js`
* Added `mapPropertyErrors` in `sw-mail-header-footer-detail/index.js` to show validation error for required fields
* Added computed `tooltipSave` to show tooltip for Save button in mail header footer detail page
