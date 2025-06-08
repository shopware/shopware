---
title: Add acl to the snippet module
issue: NEXT-8957
---
# Administration
* Added acl to the snippet module
* Added `allowInlineEdit` prop to `sw-grid-row`
* Added `allowInlineEdit` prop to `sw-grid`
* Added `src/Administration/Resources/app/administration/src/module/sw-settings-snippet/acl/index.js` file
* Added protection to all snippet module routes with the `snippet.viewer` privilege
* Added settings item for snippets is only visible inside the settings module when you have the `snippet.viewer` privilege or your and admin
* Added `getNoPermissionsTooltip` method to `src/Administration/Resources/app/administration/src/module/sw-settings-snippet/page/sw-settings-snippet-detail/index.js`
* Added `getNoPermissionsTooltip` method to `src/Administration/Resources/app/administration/src/module/sw-settings-snippet/page/sw-settings-snippet-list/index.js`
* Added `getNoPermissionsTooltip` method to `src/Administration/Resources/app/administration/src/module/sw-settings-snippet/page/sw-settings-snippet-set-list/index.js`
* Added e2e-test `src/Administration/Resources/app/administration/test/e2e/cypress/integration/settings/sw-settings-snippets/acl.spec.js`
* Added jest test `src/Administration/Resources/app/administration/test/module/sw-settings-snippet/page/sw-settings-snippet-detail.spec.js`
* Added jest test `src/Administration/Resources/app/administration/test/module/sw-settings-snippet/page/sw-settings-snippet-list.spec.js`
* Added jest test `src/Administration/Resources/app/administration/test/module/sw-settings-snippet/page/sw-settings-snippet-set-list.spec.js`
