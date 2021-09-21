---
title: Implement base code for trigger flow builder
issue: NEXT-15107
---
# Core
* Added `FlowExecutor` and `FlowState` classes at `Shopware\Core\Content\Flow`.
* Added `FlowDispatcher` class at `Shopware\Core\Content\Flow` to dispatch business event for Flow Builder.
* Added `AddOrderTagAction` class at `Shopware\Core\Content\Flow\Action`.
* Added `FlowAction` abstract class at `Shopware\Core\Content\Flow\Action`.
* Added `CustomerAware` and `OrderAware` interfaces at `Shopware\Core\Framework\Event`.
* Added function `getOrderId` into `Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent`.
* Deprecated `BusinessEventDispatcher` at `Shopware\Core\Framework\Event` which will be removed in v6.5.0.
* Added 'display_group' column into `flow_sequence` table.
* Added 'displayGroup' property into `FlowSequenceEntity` and `FlowSequenceDefinition` at `Shopware\Core\Content\Flow\Aggregate\FlowSequence`.
* Added `Sequence` class at `Shopware\Core\Content\Flow\SequenceTree`.
