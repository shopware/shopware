---
title: Fix cms saving in category module
issue: NEXT-14820
author: Jannis Leifeld
author_email: j.leifeld@shopware.com 
author_github: @jleifeld
---
# Administration
* Changed the comparison of JSON changes from `JSON.stringify` to `lodash.isEqual` in the `changeset-generator.data.js`
* Added deletion of specific cms config keys also in the `sw-category-detail/index.js`
* Removed check for `getCmsPageOverrides` in `sw-category-detail/index.js`. Now the fields are also nullable
