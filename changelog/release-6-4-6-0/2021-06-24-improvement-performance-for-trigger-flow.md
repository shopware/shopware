---
title: Improvement performance for trigger flow
issue: NEXT-15742
---
# Core
* Added `FlowIndexer`, `FlowIndexingMessage` and `FlowPayloadUpdater` class at `Shopware\Core\Content\Flow\DataAbstractionLayer`.
* Added `FlowIndexerEvent` class at `Shopware\Core\Content\Flow\Events`.
* Added `AbstractFlowLoader` interface and `FlowLoader` class at `Shopware\Core\Content\Flow`.
* Added `payload` column into table `flow`.
* Added `payload` property into `FlowEntity` and `FlowDefinition` class at `Shopware\Core\Content\Flow`.
* Added `FlowEvent` class at `Shopware\Core\Framework\Event`.
* Added `SequenceTree` and `SequenceTreeCollection` classes at `Shopware\Core\Content\Flow\SequenceTree`.
* Added `StopFlowAction` class at `Shopware\Core\Content\Flow\Action`.
