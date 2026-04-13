---
title: Search result should include products when parent product number matches
issue: 12783
---
# Core
* Changed `fetch` method in `Shopware\Elasticsearch\Product\ElasticsearchProductDefinition` to include products in search results when the parent product number matches the search term
