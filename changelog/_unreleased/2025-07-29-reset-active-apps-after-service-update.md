---
title: Reset active apps after app state change 
issue: #11515
---
# Core
* Changed `\Shopware\Core\Framework\App\ActiveAppsLoader` service definition to call `reset()` method on `AppActivatedEvent` and `AppDeactivatedEvent`.
