---
title: New language inheritance mechanism for elasticsearch
issue: NEXT-25613
flag: ES_MULTILINGUAL_INDEX
---
# Core 
* Added a new feature flag `ES_MULTILINGUAL_INDEX` in `Core/Framework/Resources/config/packages/feature.yaml`
___
# Elasticsearch
* Added a new script in `Framework/Indexing/Scripts/translated_field_sorting.groovy` to allow sorting by multiple language in painless script
* Changed `\Shopware\Elasticsearch\Framework\DataAbstractionLayer\CriteriaParser::parseSorting` to apply script sorting for translated fields
* Added class `\Shopware\Elasticsearch\Framework\Indexing\MultilingualEsIndexer` to allow indexing translatable entities in one index
* Deprecated class `\Shopware\Elasticsearch\Framework\Indexing\MultilingualEsIndexer`
* Deprecated class `\Shopware\Elasticsearch\Product\EsProductDefinition`
* Changed public const variables from `ElasticsearchProductDefinition` to `AbstractElasticsearchDefinition` 
* Deprecated these methods in `\Shopware\Elasticsearch\Framework\Indexing\IndexerOffset` due to unused:
    * `\Shopware\Elasticsearch\Framework\Indexing\IndexerOffset::setNextLanguage`
    * `\Shopware\Elasticsearch\Framework\Indexing\IndexerOffset::selectNextLanguage`
    * `\Shopware\Elasticsearch\Framework\Indexing\IndexerOffset::hasNextLanguage`
    * `\Shopware\Elasticsearch\Framework\Indexing\IndexerOffset::getLanguageId`
    * `\Shopware\Elasticsearch\Framework\Indexing\IndexerOffset::getLanguages`
* Deprecated `\Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition::buildTermQuery` as it will become abstract method from next major
* Changed method `\Shopware\Elasticsearch\Framework\ElasticsearchHelper::getIndexName` to not use `languageId` parameter to build index name
* Added a new subscriber method `\Shopware\Elasticsearch\Product\LanguageSubscriber::onLanguageWritten` to update mapping of index if there's new language created
* Deprecated subscriber method `\Shopware\Elasticsearch\Product\LanguageSubscriber::onSalesChannelWritten` as we are no longer need to add new index when a new sales channel language is created
* Changed method `\Shopware\Elasticsearch\Product\ProductSearchQueryBuilder::build` to apply new search queries when working with new index strategy (multilingual fields instead of multilingual indexes)
* Added a new DTO class `\Shopware\Elasticsearch\Product\SearchFieldConfig` that represent a search config of a searchable field
___
# Upgrade Information

## New elasticsearch data mapping structure:
* If you have your custom entities indexed, please read the [adr](../../adr/2023-04-11-new-language-inheritance-mechanism-for-opensearch.md) to match your mapping structure to the new structure and then reindex your index using `bin/console es:index`

# Next Major Version Changes
