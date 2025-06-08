---
title: Fix ES indexing behaviour with variants
issue: NEXT-16521
author: Pascal Josephy
author_email: pascal.josephy@jkweb.ch
author_github: pascaljosephy
---
# Core
*  Changed method `src/Elasticsearch/Product/ElasticsearchProductDefinition:buildCoalesce`
*  Changed method `src/Elasticsearch/Product/ElasticsearchProductDefinition:getTranslationQuery`
*  Changed method `src/Elasticsearch/Product/ElasticsearchProductDefinition:getTranslationQuery`
*  Changed method `src/Elasticsearch/Test/Product/ElasticsearchProductTest:providerCheapestPriceFilter`
*  Changed method `src/Elasticsearch/Test/Product/ElasticsearchProductTest:providerCheapestPriceSorting`
*  Changed method `src/Elasticsearch/Test/Product/ElasticsearchProductTest:testLanguageFieldsWorkSimilarToDAL`
*  Changed method `src/Elasticsearch/Test/Product/ElasticsearchProductTest:createData`
*  Added method `src/Elasticsearch/Test/Product/ElasticsearchProductTest:testLanguageFallback`
