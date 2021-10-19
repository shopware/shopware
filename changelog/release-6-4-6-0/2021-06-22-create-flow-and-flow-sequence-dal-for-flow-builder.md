---
title: Create flow and flow sequence DAL for flow builder
issue: NEXT-15110
---
# Core
* Added two new tables `flow` and `flow_sequence` to stored flow and flow sequence data for Flow Builder.
* Added entities, definition and collection for table `flow` at `Shopware\Core\Content\Flow`.
* Added entities, definition and collection for table `flow_sequence` at `Shopware\Core\Content\Flow\Aggregate\FlowSequence`.
* Added OneToMany association between `rule` and `flow_sequence`.
* Added new property `flowSequences` to `Shopware/Core/Content/Rule/RuleEntity`.
* Deprecated `EventActionRuleDefinition` at `Shopware\Core\Framework\Event\EventAction\Aggregate\EventActionRule`.
* Deprecated `EventActionSalesChannelDefinition` at `Shopware\Core\Framework\Event\EventAction\Aggregate\EventActionSalesChannel`.
* Deprecated `EventActionCollection`, `EventActionDefinition`, `EventActionEntity`, `EventActionEvents` and `EventActionSubscriber`, at `Shopware\Core\Framework\Event\EventAction`.
* Deprecated `eventActions` property in `RuleEntity` and `RuleDefinition` at `Shopware\Core\Content\Rule`.
* Deprecated `eventActions` property in `SalesChannelEntity` and `SalesChannelDefinition` at `Shopware\Core\System\SalesChannel`.
