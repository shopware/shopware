---
title: Adjust indicator designating CMS layouts already in use
issue: NEXT-13858
---
# Administration
* Removed method `isActive` from the `module/sw-cms/component/sw-cms-list-item/index.js` component. It's activity state is now solely controlled by the `active` prop.