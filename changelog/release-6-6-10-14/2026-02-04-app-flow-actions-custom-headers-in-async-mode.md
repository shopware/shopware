---
title: App flow actions custom headers in async mode 6.6.x backport #14678
issue: https://github.com/shopware/shopware/issues/3478
---
# Core
* Changed `\Shopware\Core\Framework\Webhook\Message\WebhookEventMessage` to include custom headers.
* Changed `\Shopware\Core\Framework\Webhook\Handler\WebhookEventMessageHandler` to send custom headers in webhook requests (for app flow actions in async mode).
