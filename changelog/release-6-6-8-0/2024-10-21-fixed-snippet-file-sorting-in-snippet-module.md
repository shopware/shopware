---
title: Fixed snippet file sorting in snippet module
issue: NEXT-38519
author: Marcel Brode
author_email: m.brode@shopware.com
author_github: @Marcel Brode
---
# Administration
* Changed behaviour of `sw-snippet-set-list` to order snippet files in its dropdown alphanumerically
* Deprecated `isStoreFront` in the following files, use `isStorefront` instead:
    * `src/Administration/Resources/app/administration/src/module/sw-sales-channel/view/sw-sales-channel-detail-base/index.js`
    * `src/Administration/Resources/app/administration/src/module/sw-sales-channel/page/sw-sales-channel-detail/index.js`
