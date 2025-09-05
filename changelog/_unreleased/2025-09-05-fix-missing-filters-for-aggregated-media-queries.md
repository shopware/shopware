---
title: Fix missing filters for aggregated media queries
author: Dominik Grothaus
---
# Core
* Added `Shopware\Core\Framework\DataAbstractionLayer\Event\BeforeEntityAggregationEvent` that is dispatched before an
  `EntityRepository` executes an aggregation query.
* Changed `Shopware\Tests\Unit\Core\Content\Media\Subscriber\MediaVisibilityRestrictionSubscriberTest` to subscribe to
  the new `BeforeEntityAggregationEvent` and add private media restrictions to aggregation events.
