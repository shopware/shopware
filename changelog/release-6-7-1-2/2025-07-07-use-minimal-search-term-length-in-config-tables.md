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
* Deprecated parameter `tokenMinimumLength` in `Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Tokenizer`. This parameter will be removed in v6.8.0.
___
# Upgrade Information
With this change, the minimal search term length is now loaded from the config table instead of being retrieved from the `.env` file.
This allows for more flexible configuration management and ensures that the search functionality adheres to the settings defined in the database.
