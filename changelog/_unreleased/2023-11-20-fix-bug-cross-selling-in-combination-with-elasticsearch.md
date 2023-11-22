---
title: Fix bug cross selling in combination with Elasticsearch
issue: NEXT-31716
---
# Core
* Changed methods `getMapping` and `fetch` in `src/Elasticsearch/Product/ElasticsearchProductDefinition.php` to add indexed field of property group id to match with the input data in dynamic product group.
* Changed methods `getMapping` and `fetch` in `src/Elasticsearch/Product/EsProductDefinition.php` to add indexed field of property group id to match with the input data in dynamic product group.
