---
title: Sign webhooks with the current app secret after a secret rotation
issue: #17679
author: Ghaith Olabi
author_email: m.olabi@shopware.com
author_github: @Gaitholabi
---
# Core
* Changed app webhook delivery to resolve the HMAC signing secret at delivery time (via the new `WebhookSigningSecretResolver`) instead of reusing the secret captured on the queued `WebhookEventMessage`. A webhook that is queued or retried across an app-secret rotation is now signed with the secret the app currently verifies against: the app's current secret, then the retained `deleted_apps` secret for an uninstalled app, then the secret carried on the message for messages already queued before this change. Apps no longer need to do anything — deliveries that span a rotation stop being rejected with a signature error.
