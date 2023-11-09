---
title: Use domain exception in Elasticsearch bundle
issue: NEXT-31012 
---
# Core

* Added `\Shopware\Elasticsearch\ElasticsearchException` as factory class for all Elasticsearch exceptions.
* Deprecated `\Shopware\Elasticsearch\Exception\ElasticsearchIndexingException`, `\Shopware\Elasticsearch\Exception\NoIndexedDocumentsException`, `\Shopware\Elasticsearch\Exception\ServerNotAvailableException`, `\Shopware\Elasticsearch\Exception\UnsupportedElasticsearchDefinitionException` and `\Shopware\Elasticsearch\Exception\ElasticsearchIndexingException` use `\Shopware\Elasticsearch\ElasticsearchException` instead.
___
# Next Major Version Changes
## Removal of separate Elasticsearch exception classes
Removed the following exception classes:
* `\Shopware\Elasticsearch\Exception\ElasticsearchIndexingException`
* `\Shopware\Elasticsearch\Exception\NoIndexedDocumentsException`
* `\Shopware\Elasticsearch\Exception\ServerNotAvailableException`
* `\Shopware\Elasticsearch\Exception\UnsupportedElasticsearchDefinitionException`
* `\Shopware\Elasticsearch\Exception\ElasticsearchIndexingException`
Use the exception factory class `\Shopware\Elasticsearch\ElasticsearchException` instead.