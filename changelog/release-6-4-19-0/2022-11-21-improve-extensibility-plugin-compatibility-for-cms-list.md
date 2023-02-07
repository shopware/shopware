---
title: Improve extensibility & plugin compatibility for cms list
issue: NEXT-24238
author: Marcel Brode
author_email: m.brode@shopware.com
author_github: Marcel Brode
---
# Administration
* Changed behavior of `onDuplicateCmsPage` in `src/module/sw-cms/page/sw-cms-list/index.js` to allow an additional `behavior` parameter to improve plugin extensibility
* Changed behavior of `saveCmsPage` in `src/module/sw-cms/page/sw-cms-list/index.js` to allow an additional `context` parameter to improve plugin extensibility
