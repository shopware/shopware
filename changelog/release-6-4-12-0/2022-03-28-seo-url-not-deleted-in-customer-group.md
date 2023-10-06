---
title: URL not deleted even if available Sales Channel is removed
issue: NEXT-14675
---
# Administration
* Added computed `seoUrlCriteria` in `src/module/sw-settings-customer-group/page/sw-settings-customer-group-detail/index.js` to add filter when `registrationSalesChannels` is changed.
* Added watcher `customerGroup.registrationSalesChannels` in `src/module/sw-settings-customer-group/page/sw-settings-customer-group-detail/index.js` to load `loadSeoUrls`.
* Changed method `loadSeoUrls` in `src/module/sw-settings-customer-group/page/sw-settings-customer-group-detail/index.js` to set seo urls.
