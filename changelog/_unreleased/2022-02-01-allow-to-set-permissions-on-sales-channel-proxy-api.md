---
title: Allow to set permissions on sales channel proxy api.md
issue: NEXT-19905
author: Nils Evers
author_email: evers.nils@gmail.com
author_github: NilsEvers
---
# Core
* Refactored `\Shopware\Core\Framework\Api\Controller\SalesChannelProxyController::persistPermissions` to accept context permissions from request 
___
# Administration
* Updated `StoreContextService.updateCustomerContext` to always send `ADMIN_ORDER_PERMISSIONS`
