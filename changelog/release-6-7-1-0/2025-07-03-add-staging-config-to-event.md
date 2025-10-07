---
title: Add staging config to SetupStagingEvent
---
# Core
* Changed `\Shopware\Core\Maintenance\Staging\Event\SetupStagingEvent` to include the staging configuration, this makes it easier to reuse the event in more dynamic cases where the config can not be hardcoded.
