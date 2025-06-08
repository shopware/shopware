---
title: Removal of notification titles
issue: NEXT-8334
author: Daniel Meyer
author_email: d.meyer@shopware.com 
author_github: GitEvil
---
# Administration
* Removed the superfluous title key from notification helper calls across several modules. 
    * The title is provided by `notification.mixin.js` automatically as a fallback. 
    * The title key can still be set if needed. 
    * Affected mixin calls:
        * `createNotificationInfo`
        * `createNotificationSuccess`
        * `createNotificationError`
        * `createNotificationWarning`
* Removed snippet key in `src/Administration/Resources/app/administration/src/module/sw-product/snippet/` for `en-GB.json` and `de-DE.json`
    * `sw-product.advancedPrices.deletionNotPossibleTitle`     
* Removed snippet key in `src/Administration/Resources/app/administration/src/module/sw-profile/snippet/` for `en-GB.json` and `de-DE.json`   
    * `sw-profile.index.notificationPasswordErrorTitle`
* Removed snippet key in `src/Administration/Resources/app/administration/src/module/sw-sales-channel/snippet/` for `en-GB.json` and `de-DE.json`
    * `sw-sales-channel.detail.productComparison.notificationTitleValidateSuccessful`
    * `sw-sales-channel.detail.productComparison.notificationTitleValidateError`
    * `sw-sales-channel.detail.productComparison.notificationTitlePreviewError`
