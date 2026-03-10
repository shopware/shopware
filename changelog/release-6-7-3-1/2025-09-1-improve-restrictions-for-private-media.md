---
title: Improve restrictions for private media
author: Dominik Grothaus
---
# Core
* Added `Shopware\Core\Framework\DataAbstractionLayer\Event\BeforeEntityAggregationEvent` that is dispatched before an
  `EntityRepository` executes an aggregation query.
* Changed `Shopware\Core\Content\Media\Subscriber\MediaVisibilityRestrictionSubscriber` to subscribe to the new
  `BeforeEntityAggregationEvent` and add private media restrictions to aggregation events.
* Changed `Shopware\Core\Content\Media\Subscriber\MediaVisibilityRestrictionSubscriber` to better restrict media files
  in private folders. Public media files in private folders are now excluded from search results.
