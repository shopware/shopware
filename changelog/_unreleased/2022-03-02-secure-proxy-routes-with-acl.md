---
title: Secure proxy route to switch customer with ACL
issue: NEXT-20305
author: Frederik Schmitt
author_email: f.schmitt@shopware.com
author_github: fschmtt
---
# Core
* Added migration `Shopware\Core\Migration\V6_4\Migration1646397836UpdateRolePrivilegesOfOrderCreator` for updating existing ACL roles
  ___
# API
* Changed route `api.proxy.switch-customer` to require the newly added privilege `api_proxy_switch-customer`
___
# Administration
* Changed role `order.creator` to require privilege `api_proxy_switch-customer` in `src/module/sw-order/acl/index.js`
* Added `sw-privileges.additional_permissions.routes.api_proxy_switch-customer` in `src/module/sw-users-permissions/snippet/de-DE.json`
* Added `sw-privileges.additional_permissions.routes.api_proxy_switch-customer` in `src/module/sw-users-permissions/snippet/en-GB.json`
___
# Upgrade Information
## Proxy route to switch customer requires ACL privilege
If you want to use the route `api.proxy.switch-customer` you **MUST** have the privilege `api_proxy_switch-customer`.
