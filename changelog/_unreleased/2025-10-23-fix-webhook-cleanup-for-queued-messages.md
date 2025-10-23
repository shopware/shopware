---
title: Fix webhook cleanup for queued webhook event logs
---
# Core
* Changed `\Shopware\Core\Framework\Webhook\Service\WebhookCleanup::removeOldLogs()` to also delete webhook event logs in `queued` state after double the configured log retention time, assuming that the queue message is lost and the webhook won't be executed anymore. This prevents the webhook event log table from growing indefinitely.
* Changed `\Shopware\Core\Framework\Webhook\Handler\WebhookEventMessageHandler` to not fail the webhook delivery when the webhook log is already deleted.