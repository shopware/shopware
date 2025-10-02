---
title: config endpoints cached indefinitely
issue: #11314
---
# Core
* Changed `src/Administration/Controller/UserConfigController.php` to allow updating user config with a PATCH request method
___
# Administration
* Changed `src/core/factory/cache-adapter.factory.js` to cache the config endpoints indefinitely
* Changed `src/core/service/api/user-config.api.service.ts` to use a PATCH instead of a POST method
