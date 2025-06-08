---
title: Add timestamp to synchronous webhook payload
issue: NEXT-18273
---
# Core
* Changed `\Shopware\Core\Framework\Webhook\WebhookDispatcher::callWebhooksSynchronous()` to add current timestamp to the webhook payload, as already happened for asynchronous dispatched webhooks. When your app receives webhook please verify the timestamp property as described in the [docs](https://developer.shopware.com/docs/guides/plugins/apps/app-base-guide#webhooks).
