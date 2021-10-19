---
title: Improvement flow builder for BE
issue: NEXT-16555
---
# Core
* Changed namespace of `FlowActionDefinition`, `FlowActionCollector` and `FlowActionCollectorResponse` classes to `Shopware\Core\Content\Flow\Api`.
* Changed namespace of `FlowIndexer`, `FlowIndexingMessage` and `FlowPayloadUpdater` classes to `Shopware\Core\Content\Flow\Indexing`.
* Changed `SequenceTreeBuilder` class name to `FlowBuilder` and moved to `Shopware\Core\Content\Flow\Dispatching`.
* Changed namespace of `FlowDispatcher`, `FlowExecutor`, `FlowState`, `FlowLoader` and `AbstractFlowLoader` classes to `Shopware\Core\Content\Flow\Dispatching`.
* Changed namespace of `AddCustomerTagAction`, `AddOrderTagAction`, `CallWebhookAction`, `FlowAction`, `FlowActionDefinition`, `GenerateDocumentAction`, `RemoveCustomerTagAction`, `RemoveOrderTagAction`, `SendMailAction`, `SetOrderStateAction`, `StopFlowAction` and `` classes to `Shopware\Core\Content\Flow\Dispatching\Action`.
* Changed namespace of `Sequence` class to `Shopware\Core\Content\Flow\Dispatching\Struct`.
* Changed `SequenceTree` class name to `Flow` and moved to `Shopware\Core\Content\Flow\Dispatching\Struct`.
* Removed `SequenceTreeCollection` class from `Shopware\Core\Content\Flow\SequenceTree`.
* Added `invalid` property to `FlowEntity` and `FlowDefinition` at `Shopware\Core\Content\Flow`.
* Added `IfSequence` and `ActionSequence` classes at `Shopware\Core\Content\Flow\Dispatching\Struct`.
* Added `ExecuteSequenceException` class at `Shopware\Core\Content\Flow\Exception`.
* Changed flow actions api route from `/api/_info/actions.json` to `/api/_info/flow-actions.json`.
* Added `FlowEventAware` interface at `Shopware\Core\Framework\Event`.
___
# Upgrade Information

## Update `/api/_info/events.json` API
* Added `aware` property to `BusinessEventDefinition` class at `Shopware\Core\Framework\Event`.
* Deprecated `mailAware`, `logAware` and `salesChannelAware` properties in `BusinessEventDefinition` class at `Shopware\Core\Framework\Event`.
### Response of API
* Before:
```json
[
    {
        "name": "checkout.customer.before.login",
        "class": "Shopware\\Core\\Checkout\\Customer\\Event\\CustomerBeforeLoginEvent",
        "mailAware": false,
        "logAware": false,
        "data": {
            "email": {
                "type": "string"
            }
        },
        "salesChannelAware": true,
        "extensions": []
    }
]
```
* After:
```json
[
    {
        "name": "checkout.customer.before.login",
        "class": "Shopware\\Core\\Checkout\\Customer\\Event\\CustomerBeforeLoginEvent",
        "data": {
            "email": {
                "type": "string"
            }
        },
        "aware": [
            "Shopware\\Core\\Framework\\Event\\SalesChannelAware"
        ],
        "extensions": []
    }
]
```
