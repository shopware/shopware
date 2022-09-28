---
title: Fix custom field in the media display
issue: NEXT-22512
author: Luka Brlek
author_email: l.brlek@shopware.com
---
# Administration
* Deprecated in `Resources/app/administration/src/module/sw-media/component/sidebar/sw-media-quickinfo/index.js`:
    * `customFieldSetRepository`
    * `getCustomFieldSets` use `loadCustomFieldSets` instead
* Added `loadCustomFieldSets` to load data via `customFieldDataProviderService` in `Resources/app/administration/src/module/sw-media/component/sidebar/sw-media-quickinfo/index.js`

