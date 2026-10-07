---
title: Log system activities on a dedicated Monolog channel
issue: 21150
---
# Core
* Added `Shopware\Core\Framework\Log\SystemActivitySubscriber`, which logs user and integration creation, plugin and app uploads, and plugin and app installation, updates, activation, deactivation, and removal at `info` level on the `system_activity` Monolog channel.
* Added actor details and available plugin or app metadata to those records. Actors are `user` (`userId`, `username`), `integration` (`integrationAccessKey`), or `system` for CLI commands and background jobs. Plugin updates include `previousPluginVersion`. App removal includes `keepUserData`. `null` fields, passwords, and secret access keys are omitted.
* Added default storage of these records in `log_entry` through the existing buffered business-event handler. Route `system_activity` to another handler in `config/packages/monolog.yaml`. To keep business-event database logging and skip system activities, override the `business_event_handler_buffer` handler channels to `['business_events']`.
