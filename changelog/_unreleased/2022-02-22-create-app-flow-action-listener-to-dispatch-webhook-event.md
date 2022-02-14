---
title: Create App flow action listener to dispatch Webhook event
issue: NEXT-19012
---
# Core
* Added string field `url` into `Shopware\Core\Framework\App\Aggregate\FlowAction\AppFlowActionDefinition`
* Added property `url` into `Shopware\Core\Framework\App\Aggregate\FlowAction\AppFlowActionEntity`
* Added event `Shopware\Core\Framework\App\Event\AppFlowActionEvent`
* Added listener `Shopware\Core\Framework\App\FlowAction\AppFlowActionListener`
* Added method `updateAppFlowActionWebhooks` into `Shopware\Core\Framework\App\Lifecycle\Persister\WebhookPersister`
* Added new class `Shopware\Core\Framework\App\FlowAction\AppFlowActionProvider`
* Changed method `updateApp` in `Shopware\Core\Framework\App\Lifecycle\AppLifecycle` to update webhook when update app
* Changed method `load` in `Shopware\Core\Content\Flow\Dispatching\FlowLoader` to add `AppFlowActionListener` to app flow actions event
* Changed method `getSubscribedEvents` in `Shopware\Core\Content\Flow\Indexing\FlowIndexer`

