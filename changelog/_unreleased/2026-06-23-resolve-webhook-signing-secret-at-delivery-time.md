---
title: Resolve webhook signing secret at delivery time
author: Ghaith Olabi
author_email: m.olabi@shopware.com
author_github: @Gaitholabi
---
# Core
* Added `Shopware\Core\Framework\Webhook\Service\WebhookSigningSecretResolver`, which resolves the app HMAC secret used to sign an outgoing webhook at delivery time instead of reusing the secret captured when the message was enqueued. A webhook queued or retried across an app-secret rotation is now signed with the secret the app currently verifies against, fixing persistent `Signature could not be verified` failures after a rotation.
* Changed `Shopware\Core\Framework\Webhook\Service\WebhookDeliveryService::buildRequest()` to sign via `WebhookSigningSecretResolver` rather than the secret carried on the message.
* Added a nullable `appName` to `Shopware\Core\Framework\Webhook\Message\WebhookEventMessage` so the signing secret can be recovered from `deleted_apps` when the app row is already gone at delivery time (e.g. the `app.deleted` webhook).
