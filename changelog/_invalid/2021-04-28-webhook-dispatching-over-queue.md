---
title: Webhook dispatching over queue
issue: NEXT-14363
flag: FEATURE_NEXT_14363
---
# Core
* Added `src\Core\Framework\Webhook\Message\WebhookEventMessage` class
* Added `src\Core\Framework\Webhook\Handler\WebhookEventMessageHandler` class
* Changed method `callWebhooks` in `src\Core\Framework\Webhook\WebhookDispatcher` to handle webhooks asynchronous over the queue
