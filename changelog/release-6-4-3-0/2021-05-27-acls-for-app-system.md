---
title: ACLs for app system
issue: NEXT-14362
---
# Administration
* Added ACL to my-apps module
* Added new service `AppAclService` which is responsible to add app permissions 
* Changed method `getNavigationFromApp` in `src/app/service/menu.service.js` to add privilege to the app navigation
* Changed method `additionalPermissions` and added method `appPermissions` in `src/module/sw-users-permissions/components/sw-users-permissions-additional-permissions/index.js` to get `appPermissions`
* Added block `sw_users_permissions_additional_permissions_app_privileges` in `src/module/sw-users-permissions/components/sw-users-permissions-additional-permissions/sw-users-permissions-additional-permissions.html.twig`
___
# Core
* Added a new Migration `src/Core/Migration/Migration1625304609UpdateRolePrivileges.php` that update acl role privileges
* Changed method `install` in `src/Core/Framework/App/Lifecycle/AppLifecycle.php` to add app privileges to acl role privileges
* Changed method `validate` in `src/Core/Framework/Api/Acl/AclAnnotationValidator.php` to validate acl privileges for app
