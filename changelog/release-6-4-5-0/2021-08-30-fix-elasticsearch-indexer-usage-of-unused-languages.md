---
title: Fix Elasticsearch indexer usage of unused languages
issue: NEXT-16928
author: Sebastian Seggewiss
author_email: s.seggewiss@shopware.com 
author_github: seggewiss
---
# Core
* Added `\Shopware\Elasticsearch\Framework\Indexing\Event\ElasticsearchIndexerLanguageCriteriaEvent`
* Added filter to `\Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexer::getLanguages`, to not use unused languages
* Added `\Shopware\Elasticsearch\Framework\Indexing\Event\ElasticsearchIndexerLanguageCriteriaEvent` dispatch to `\Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexer::getLanguages`
