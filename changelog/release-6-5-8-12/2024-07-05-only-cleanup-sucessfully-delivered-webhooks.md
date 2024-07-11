---
title: Only cleanup successfully delivered or permanently failed webhook events
issue: NEXT-37072
---
# Core
* Changed `\Shopware\Core\Framework\Webhook\Service\WebhookCleanup` to only cleanup successfully delivered or permanently failed webhook events, thus preventing race conditions where the cleanup task might delete webhook event entries that are still running or being retried.

