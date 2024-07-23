---
title: Elasticsearch with special chars
issue: NEXT-37518
---
# Core
* Added a new parameter `shopware.search.preserved_chars` to the `\Shopware\Core\Framework\DataAbstractionLayer\Search\Term\TokenizerInterface` to allow special characters in the tokenized string.
* Changed mapping of `\Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition::SEARCH_FIELD` to have a default analyzer `sw_whitespace_analyzer`
* Added a new analyzer `sw_whitespace_analyzer` in `src/Elasticsearch/Resources/config/packages/elasticsearch.yaml`
* Changed tokenizer of analyzer `sw_english_analyzer` and `sw_german_analyzer` to `sw_whitespace_tokenizer` in `src/Elasticsearch/Resources/config/packages/elasticsearch.yaml`
___
# Upgrade Information
## Elasticsearch with special chars
* To apply searching by Elasticsearch with special chars, you would need to update your ES index mapping by running: `es:index`

## New parameter `shopware.search.preserved_chars` when tokenizing
* By default, the parameter `shopware.search.preserved_chars` is set to `['-', '_', '+', '.', '@']`. You can add or remove special characters to this parameter by override it in `shopware.yaml` to allow them when tokenizing string.
