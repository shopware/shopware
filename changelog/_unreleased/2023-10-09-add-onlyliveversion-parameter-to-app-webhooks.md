---
title: Add onlyLiveVersion parameter to app webhooks
issue: NEXT-25998
---
# Core
* Added new parameter `onlyLiveVersion` to webhooks in app manifest.xml file. By default set to false, when set to true the webhook will only be called for entities written to the database with live version id (`Shopware\Core\Defaults::LIVE_VERSION`), additionally the webhook payload will be filtered to only contain entries with live version id.
