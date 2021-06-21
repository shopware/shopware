---
title: Improve AppDeletion workflow
issue: NEXT-14797
---
# Core
* Added `src/Core/Framework/App/ScheduledTask/DeleteCascadeAppsTask` class.
* Added `src/Core/Framework/App/ScheduledTask/DeleteCascadeAppsHandler` class.
* Added method `deleteSoftRole` in `Shopware/Core/Framework/App/Lifecycle/Persister/PermissionPersister.php`.
* Changed method `removeAppAndRole` in `Shopware/Core/Framework/App/Lifecycle/AppLifecycle.php` to can delete soft acl role and integration belongs to app which was removed.
