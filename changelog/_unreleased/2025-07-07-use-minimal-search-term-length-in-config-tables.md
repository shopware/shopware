---
title: Use minimal search term length in config tables
issue: 8018
---
# Core
* Changed these classes to load the minimal search term length from the config table and pass it to the Tokenizer.
   * `Shopware\Core\Content\Product\SearchKeyword\ProductSearchKeywordAnalyzer`
   * `Shopware\Core\Content\Product\SearchKeyword\ProductSearchTermInterpreter`
   * `Shopware\Core\Framework\DataAbstractionLayer\Search\Term\SearchTermInterpreter`
   * `Shopware\Elasticsearch\Product\ProductSearchQueryBuilder`
* Changed `Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Filter\TokenFilter` to use `SearchConfigLoader` to load filter config.
* Changed `load` method in `Shopware\Elasticsearch\Product\SearchConfigLoader` to load min search length and excluded terms.
