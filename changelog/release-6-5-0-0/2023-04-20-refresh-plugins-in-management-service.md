---
title: Refresh plugins in management service
issue: NEXT-26255
author: Frederik Schmitt
author_email: f.schmitt@shopware.com
author_github: fschmtt
---
# Core
* Changed `Shopware\Core\Framework\Plugin\PluginManagementService` to refresh the plugins after downloading, uploading or deleting a plugin.
___
# Administration
* Changed `src/module/sw-first-run-wizard/view/sw-first-run-wizard-paypal-info/index.js` to no longer manually refresh the plugins after downloading and before installing the PayPal plugin.
