---
title: Fix async webhook dispatching for app lifecycle events
issue: NEXT-20885
---
# Core
* Changed `\Shopware\Core\Framework\Webhook\Handler\WebhookEventMessageHandler` to not fail if webhook entity for the received message was deleted in the meantime.
* Changed `\Shopware\Core\Framework\Webhook\WebhookDispatcher` to always dispatch AppLifecycleEvents synchronously and to add unique `eventId` identifier to each webhook.
___
# Upgrade Information
## Webhooks contain unique event identifier
All webhooks now contain a unique identifier that allows your app to identify the event.
The identifier can be found in the JSON-payload under the `source.eventId` key.

```json
{
    "source": {
        "url": "http:\/\/localhost:8000",
        "appVersion": "0.0.1",
        "shopId": "dgrH7nLU6tlE",
        "eventId": "7b04ebe416db4ebc93de4d791325e1d9"
    }
}

```
This identifier is unique for each original event, it will not change if the same request is sent multiple times due to retries, 
because your app maybe did not return a successful HTTP-status on the first try.
