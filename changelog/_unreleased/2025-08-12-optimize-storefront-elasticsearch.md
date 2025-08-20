---
title: Optimize storefront elasticsearch
issue: 11130
---
# Core
* Added new bool property `useForSorting` (default as `false`) in `\Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField` to indicate this field could be used for sorting when querying against Elasticsearch.
* Changed `\Shopware\Core\Content\Product\ProductDefinition` to mark `name` field as `useForSorting = true`.
* Added new getter for `salesChannelId` and `visibility` in `\Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter`
* Added new getter for `value` and `field` in `\Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter`
* Added a new bool property `lastMessage` in `\Shopware\ElasticSearch\Framework\Indexing\ElasticsearchIndexingMessage` to indicate whether the message is the last one in the queue.
* Added a new event `\Shopware\ElasticSearch\Framework\Indexing\Event\ElasticsearchIndexingFinishedEvent` to signal the end of the indexing process.
* Changed `\Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexer::handleIndexingMessage` to dispatch `ElasticsearchIndexingFinishedEvent` when the last message is processed. 
* Changed `\Shopware\Elasticsearch\Product\ElasticsearchProductDefinition` to define new mapping `visibility_<sales_channel_id>` field to optimize for reading performance.
* Added `Shopware\Elasticsearch\Product\ProductCriteriaParser` to optimize search query performance when searching against products index.
* Changed `\Shopware\Elasticsearch\Framework\DataAbstractionLayer\CriteriaParser` to use keyword field whenever possible to optimize search query performance
* Added a new listener `\Shopware\Elasticsearch\Product\ElasticsearchOptimizeSwitch` to switch the flag that indicates we should use the optimized search query parser.
___
# Upgrade Information
## New Elasticsearch enhancement for optimized storefront searching and sorting

This change introduces a new Elasticsearch enhancement that optimizes storefront searching and sorting. It includes the following key features:

- A new boolean property `useForSorting` in `TranslatedField` to indicate if a field can be used for sorting in Elasticsearch queries.
- Avoid using nested query when searching against Elasticsearch because its performance is not optimal.

These changes require a full re-index, you need to update your Elasticsearch index's mapping by running the following command:

```bash
bin/console es:index
```

And the new implementation will be switched and ready to use after the re-indexing is completed.