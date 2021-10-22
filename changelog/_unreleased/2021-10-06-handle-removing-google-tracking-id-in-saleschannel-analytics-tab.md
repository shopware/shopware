---
title: Removing a set Google tracking-ID in SalesChannel analytics tab
author: Ioannis Pourliotis
author_email: dev@pourliotis.de
author_github: @PheysX
---
# Administration
* Changed `onSave` method to handle Google tracking-ID deletion in `src/Administration/Resources/app/administration/src/module/sw-sales-channel/page/sw-sales-channel-detail/index.js`.
* Changed `Shopware.Context.api` to the shorter version `Context.api` in `src/Administration/Resources/app/administration/src/module/sw-sales-channel/page/sw-sales-channel-detail/index.js`.
* Changed jest test `src/Administration/Resources/app/administration/test/module/sw-sales-channel/page/sw-sales-channel-detail.spec.js` to test analytics association.
