---
title: Fix ES index language inheritance issue
issue: 12433
---
# Core
* Changed `\Shopware\Elasticsearch\Product\ElasticsearchProductDefinition::fetchProducts` to always fetch default translation of products to ensure fall language inheritance in Elasticsearch index