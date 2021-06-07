---
title: Webhook Retry Handling
issue: NEXT-14683
flag: FEATURE_NEXT_14363
---
# Core
* Added `src/Core/Framework/Webhook/Event/RetryWebhookMessageFailedEventEvent` class
* Added `src/Core/Framework/Webhook/Subscriber/RetryWebhookMessageFailedSubscriber` class
* Added `src/Core/Framework/Webhook/EventLog/WebhookEventLogCollection` class
* Added `src/Core/Framework/Webhook/EventLog/WebhookEventLogCollection` class
* Added `src/Core/Framework/Webhook/EventLog/WebhookEventLogDefinition` class
* Changed method `handle` in `Shopware\Core\Framework\Webhook\Handler\WebhookEventMessageHandler` to handle the webhook message fail
