---
title: Category and shop page assignment in CMS layout module
issue: NEXT-11389
author: Tobias Berge
author_email: t.berge@shopware.com 
author_github: tobiasberge
---
# Administration
* Added new component `sw-cms-layout-assignment-modal` in `Resources/app/administration/src/module/sw-cms/component/sw-cms-layout-assignment-modal`
* Added additional `sw-sidebar-item` "layoutAssignment" in `Resources/app/administration/src/module/sw-cms/component/sw-cms-sidebar/sw-cms-sidebar.html.twig`
* Added new method `onOpenLayoutAssignment` in `Resources/app/administration/src/module/sw-cms/component/sw-cms-sidebar/sw-cms-sidebar.html.twig`
* Added privilege `category:update` for `editor` role in `src/Administration/Resources/app/administration/src/module/sw-cms/acl/index.js`
* Added new data prop `showLayoutAssignmentModal` in `Resources/app/administration/src/module/sw-cms/sw-cms-detail/index.js`
* Added new data prop `previousRoute` in `Resources/app/administration/src/module/sw-cms/sw-cms-detail/index.js`
* Added navigation guard `beforeRouteEnter` in `Resources/app/administration/src/module/sw-cms/sw-cms-detail/index.js`
* Added new method `openLayoutAssignmentModal` in `Resources/app/administration/src/module/sw-cms/page/sw-cms-detail/index.js`
* Added new method `closeLayoutAssignmentModal` in `Resources/app/administration/src/module/sw-cms/page/sw-cms-detail/index.js`
* Added new method `onOpenLayoutAssignment` in `Resources/app/administration/src/module/sw-cms/page/sw-cms-detail/index.js`
* Added new method `onConfirmLayoutAssignment` in `Resources/app/administration/src/module/sw-cms/page/sw-cms-detail/index.js`
* Changed criteria in `loadPageCriteria` and added association `categories` in `Resources/app/administration/src/module/sw-cms/page/sw-cms-detail/index.js`
