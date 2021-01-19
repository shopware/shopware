---
title: Add 'all' role for ACL privileges
issue: NEXT-12804 
---
# Core
* Added ```ALL_ROLE_KEY``` constant to ```Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition```
* Added possibility to add a privilege to all user by the ```ALL_ROLE_KEY``` in the ```enrichPrivileges``` method for plugins.
