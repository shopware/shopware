---
title: Fix CMS content slot overrides for modules and languages
author: Benedikt Schulze Baek
author_email: b.schulze-baek@shopware.com
author_github: @bschulzebaek
---
# Administration
* Changed CMS slot config to now correctly apply config overrides on individual pages (Category, Landing, Product) and languages.
* Deprecated method `resetCmsPageState` in `module/sw-cms/page/sw-cms-detail/index.js`. Use `resetRelatedStores` instead.
