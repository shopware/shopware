---
title: ES should work correctly with ScoreQuery
issue: 8471
---
# Core
* Changed `addQueries` method in `Shopware\Elasticsearch\Framework\ElasticsearchHelper` to ensure that the score in `ScoreQuery` is correctly applied to the search query.
