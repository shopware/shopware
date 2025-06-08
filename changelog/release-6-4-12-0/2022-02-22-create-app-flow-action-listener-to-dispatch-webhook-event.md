---
title: Update FlowExecutor to dispatch Webhook event
issue: NEXT-19012
---
# Core
* Added string field `url` into `Shopware\Core\Framework\App\Aggregate\FlowAction\AppFlowActionDefinition`
* Added property `url` into `Shopware\Core\Framework\App\Aggregate\FlowAction\AppFlowActionEntity`
* Added event `Shopware\Core\Framework\App\Event\AppFlowActionEvent`
* Added function `updateAppFlowActionWebhooks` into `Shopware\Core\Framework\App\Lifecycle\Persister\WebhookPersister`
* Added function `updateWebhooksFromArray` into `Shopware\Core\Framework\App\Lifecycle\Persister\WebhookPersister`
* Added exception `Shopware\Core\Framework\App\Exception\InvalidAppFlowActionVariableException`
* Added class `Shopware\Core\Framework\App\FlowAction\AppFlowActionProvider`
* Changed function `updateApp` in `Shopware\Core\Framework\App\Lifecycle\AppLifecycle` to update webhook when update app
* Changed function `getSubscribedEvents` in `Shopware\Core\Content\Flow\Indexing\FlowIndexer`.
* Added property `appFlowActionId` into `Shopware\Core\Content\Flow\Dispatching\Struct\ActionSequence`
* Added parameter `appFlowActionId` into method `Shopware\Core\Content\Flow\Dispatching\Struct\Sequence::createAction()`
* Changed method `executeAction` in `Shopware\Core\Content\Flow\Dispatching\FlowExecutor` to dispatcher correct event.
* Changed method `update` in `Shopware\Core\Content\Flow\Indexing\FlowPayloadUpdater` to add `app_flow_action_id` value to payload of flow.
