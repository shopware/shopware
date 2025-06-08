---
title: Separate requests for plugin download and update
issue: NEXT-12957
---
# Core
* Changed the `downloadPlugin` method in `\Shopware\Core\Framework\Store\Api\StoreController` so that it only executes
  the plugin download and does not trigger an update anymore
___
# API
* Changed the `api.custom.store.download` route, so that it only executes the plugin download and does not trigger an
  update anymore
___
# Administration
* Changed the `downloadPlugin` method in `src/core/service/api/store.api.service.js` so that it triggers a request to
  the `api.action.plugin.update` route after a plugin has been downloaded successfully
___
# Upgrade Information
If you're using the `api.custom.store.download` route, be aware that its behaviour will change when `platform` >=
v6.4.0.0  is in use. The route will no longer trigger a plugin update. 
In case you'd like to trigger a plugin update, you'll need to dispatch another request to the
`api.action.plugin.update` route.
