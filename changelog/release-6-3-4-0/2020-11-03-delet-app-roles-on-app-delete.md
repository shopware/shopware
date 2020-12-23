---
title: Delete app roles when app gets deleted
issue: NEXT-11720
author: Jonas Elfering
---
# Core
* Changed `\Shopware\Core\Framework\App\Lifecycle\AppLifecycle::delete()` method to additionally delete the AclRoles of the deleted app.
