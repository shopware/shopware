---
title: Add a scheduled task to clean up old webhook logs
issue: NEXT-24530
---
# Core
* Add a core configuration value `core.webhook.entryLifetimeSeconds`
* Added a scheduled task to clean up old webhook log entries based on the configuration value 
