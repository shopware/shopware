Elasticsearch Component
==============

The Elasticsearch component is the elasticsearch adapter for the shopware/core.
It contains the indexing of entities and an adapter for the entity search.

This repository is considered **read-only**. Please send pull requests
to our [main Shopware repository](https://github.com/shopware/shopware).

Search architecture
-------------------

If you're adding a new search field, tuning a ranking, or investigating why a
query returned a particular result, start with [Resources/doc/SEARCH_ARCHITECTURE.md](Resources/doc/SEARCH_ARCHITECTURE.md).
It walks the end-to-end pipeline (tokenization → analyzer chains → query
construction → BM25 → minScore), documents the `buildTextFieldConfig` flag
combinations, and includes an investigation cookbook for ranking surprises.

The integration test
[`tests/integration/Elasticsearch/Product/SearchCasesTest.php`](../../tests/integration/Elasticsearch/Product/SearchCasesTest.php)
is the executable mirror of the architecture doc — every documented behavior
should have a matching scenario there.

Resources
---------

  * [Documentation](https://developer.shopware.com)
  * [Contributing](https://developer.shopware.com/docs/resources/guidelines/code/contribution.html)
  * [Report issues](https://github.com/shopware/shopware/issues) and
    [send Pull Requests](https://github.com/shopware/shopware/pulls)
    in the [main Shopware\Core repository](https://github.com/shopware/shopware)
