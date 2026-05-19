---
title: Fix webhook dispatching for not versioned events
issue: 7858
---
# Core
* Changed `\Shopware\Core\Framework\Webhook\Service\WebhookManager::filterWebhooksByLiveVersion` to not filter out events that are not versioned, even if the webhook is configured with the `onlyLiveVersion` flag. Thus events without a versionId are now dispatched even when the flag is active fixing a bug that those webhooks would never be sent, meaning for those events the flag has no effect anymore. 
