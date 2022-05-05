---
title: Fix app installation if `app.all` acl role exist
issue: NEXT-20951
---
# Core
* Changed `\Shopware\Core\Framework\App\Lifecycle\AppLifecycle::install()` to update the acl roles with permission `app.all` in system scope, thus fixing a problem that the acl roles could not be written in user scope.
