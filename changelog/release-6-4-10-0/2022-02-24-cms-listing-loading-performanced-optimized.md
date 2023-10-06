---
title: CMS listing loading performanced optimized
issue: NEXT-19996
---
# Administration
* Added aggregations to `src/module/sw-cms/page/sw-cms-list/index.js` to consider product & category page instead of loading whole EntityCollections per cmsPage
* Remove the 'product' association in the cmsPage criteria of `src/module/sw-cms/page/sw-cms-list/index.js`