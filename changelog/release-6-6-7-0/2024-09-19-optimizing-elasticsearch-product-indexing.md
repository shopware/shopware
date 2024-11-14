---
title: Optimizing Elasticsearch product indexing
issue: NEXT-38038
---
# Core
* Changed `\Shopware\Elasticsearch\Product\ElasticsearchProductDefinition::fetch` to optimize fetching products when doing elasticsearch index
* Added new service `\Shopware\Core\System\Language\SalesChannelLanguageLoader` to load sales channel languages