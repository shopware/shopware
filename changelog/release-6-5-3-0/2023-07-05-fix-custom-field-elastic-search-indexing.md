---
title: Fix elasticsearch indexing of admin custom field sets
issue: NEXT-28237
---
# Core
* Changed `\Shopware\Elasticsearch\Product\ElasticsearchProductDefinition` to automatically add custom field sets defined in the administration to the mapping of the elasticsearch index.
