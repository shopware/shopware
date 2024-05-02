---
title: Improve elasticsearch
issue: NEXT-35071
---
# Core
* Added a new event `\Shopware\Elasticsearch\Framework\Indexing\Event\ElasticsearchIndexIteratorEvent` to allow customizing the Elasticsearch index iterator. This event is dispatched in the `ElasticsearchIndexIterator::iterate` method.
* Added a new event `\Shopware\Elasticsearch\Framework\DataAbstractionLayer\Event\ElasticsearchEntityAggregatorSearchedEvent` to allow manipulating the search result of the Elasticsearch entity aggregator. This event is dispatched in the `ElasticsearchEntityAggregator::aggregate` method.
* Added a new event `\Shopware\Elasticsearch\Framework\DataAbstractionLayer\Event\ElasticsearchEntitySearcherSearchedEvent` to allow manipulating the search result of the Elasticsearch entity searcher. This event is dispatched in the `ElasticsearchEntitySearcher::search` method.