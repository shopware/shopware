---
title: Fix parent role will be checked when not having
issue: NEXT-12816
---
# Administration
* Changed private method `_existsPrivilege` of `privilege.service.js` to public method `existsPrivilege`
* Added `ignoreMissingPrivilege` parameter `areSomeChildrenRolesSelected` of `src/Administration/Resources/app/administration/src/module/sw-users-permissions/components/sw-users-permissions-permissions-grid/index.js` with a default value of `true`
* Added `parentRoleHasChildRoles` method to `src/Administration/Resources/app/administration/src/module/sw-users-permissions/components/sw-users-permissions-permissions-grid/index.js`
