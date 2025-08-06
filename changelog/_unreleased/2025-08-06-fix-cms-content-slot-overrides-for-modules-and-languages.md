---
title: Fix CMS content slot overrides for modules and languages
author: Benedikt Schulze Baek
author_email: b.schulze-baek@shopware.com
author_github: @bschulzebaek
---
# Administration
* CMS slot config overrides are now correctly applied on individual pages (Category layout, Product layout) and for non-default language configurations.
* Deprecated method `resetCmsPageState` in `module/sw-cms/page/sw-cms-detail/index.js`. Use `resetRelatedStores` instead.
