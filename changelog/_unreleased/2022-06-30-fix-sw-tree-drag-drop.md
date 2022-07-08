---
title: Fix sw-tree drag&drop
issue: NEXT-21789
author: Marcel Brode
author_email: m.brode@shopware.com
author_github: Marcel Brode
---
# Administration
* Changed `administration/src/app/directive/dragdrop.directive.js` to early return, when no position coordinates where available
* Changed `administration/src/app/component/tree/sw-tree/index.js` to rework the drag&drop, so it doesn't randomly rearrange its items anymore
* Changed `administration/src/module/sw-category/component/sw-category-tree/index.js` to add a debouncer to saving, so it doesn't "flicker" multiple times after rearranging items
