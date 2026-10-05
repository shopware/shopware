---
title: Log system activities on a dedicated Monolog channel
issue: 21150
---
# Core
* Added `Shopware\Core\Framework\Log\SystemActivitySubscriber` to log user and integration creation, plugin and app uploads, and plugin and app installation, updates, activation, deactivation, and removal at `info` level on the `system_activity` Monolog channel.
* Records include the actor (`user` with `userId` and `username`, `integration` with `integrationAccessKey`, or `system` for CLI commands and background jobs) and available plugin or app metadata. Update records include `previousPluginVersion` for plugins. App removal records include `keepUserData`. `null` fields, passwords, and secret access keys are omitted.
* These records are stored in `log_entry` by the existing buffered business-event handler. Route `system_activity` to another handler in `config/packages/monolog.yaml`. To keep business-event database logging and skip system activities, override the `business_event_handler_buffer` handler channels to `['business_events']`.
