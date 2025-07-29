---
title: Reset active apps after service update
issue: #11515
---
# Core
* Changed `\Shopware\Core\Service\Subscriber\SystemUpdateSubscriber` to reset the active app state, so that follow up steps during the system update run on the latest app state.
