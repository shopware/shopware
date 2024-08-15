---
title: Elasticsearch with special chars
issue: NEXT-34674
---
# Core
* Added a new parameter `shopware.search.preserved_chars` to the `\Shopware\Core\Framework\DataAbstractionLayer\Search\Term\TokenizerInterface` to allow special characters in the tokenized string.
* Changed mapping of `\Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition::SEARCH_FIELD` to have a default analyzer `sw_whitespace_analyzer`
* Changed `\Shopware\Elasticsearch\Product\SearchFieldConfig` to have a new boolean property `andLogic` and new getter/setter for `ranking` property
* Added a new analyzer `sw_whitespace_analyzer` in `src/Elasticsearch/Resources/config/packages/elasticsearch.yaml`
* Changed tokenizer of analyzer `sw_engine_analyzer` and `sw_german_analyzer` to `sw_whitespace_tokenizer` in `src/Elasticsearch/Resources/config/packages/elasticsearch.yaml`
* Added new class `Shopware\Elasticsearch\TokenQueryBuilder` to build an elasticsearch query for a single token
* Changed `\Shopware\Elasticsearch\Product\ProductSearchQueryBuilder::build` to use the new `TokenQueryBuilder`
___
# Upgrade Information
## Elasticsearch with special chars
* To apply searching by Elasticsearch with special chars, you would need to update your ES index mapping by running: `es:index`

## New parameter `shopware.search.preserved_chars` when tokenizing
* By default, the parameter `shopware.search.preserved_chars` is set to `['-', '_', '+', '.', '@']`. You can add or remove special characters to this parameter by override it in `shopware.yaml` to allow them when tokenizing string.