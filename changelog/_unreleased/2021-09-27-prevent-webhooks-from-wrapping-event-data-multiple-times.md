---
title: Prevent Webhooks from wrapping event data multiple times
issue: NEXT-17429
---
# Core
* Changed behaviour of `\Shopware\Core\Framework\Webhook\WebhookDispatcher::dispatch`. Event data will not be wrapped multiple times no more if more than one webhook subscribes to an event.
