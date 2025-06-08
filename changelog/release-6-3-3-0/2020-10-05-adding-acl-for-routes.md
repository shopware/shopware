---
title: Adding ACL for Routes
issue: NEXT-10714 
---
# Core
*  Added `Shopware\Core\Framework\Api\Controller\AclController` to provide all core privileges
*  Added `Shopware\Core\Framework\Routing\Annotation\Acl` to `Shopware\Core\System\SystemConfig\Api\SystemConfigController`, `Shopware\Core\Framework\Api\Controller\AclController`, `Shopware\Core\Framework\Api\Controller\CacheController` and `Shopware\Core\Framework\Api\Controller\UserController`
*  Added Event `Shopware\Core\Framework\Api\Acl\Event\AclGetAdditionalPrivilegesEvent`
___
# API
*  Added ACL permission check to protected Routes. A user needs to have admin rights or needs the route privilege to call a protected route.
___
# Administration
*  Added `sw-users-permissions-detailed-additional-permissions` component
*  Added `acl.api.service.js` to get core privileges

