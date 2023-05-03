---
title: Update symfony to 6.1
issue: NEXT-23917
---
# Core
* Changed the minimum required PHP version to 8.1.
* Changed the used symfony version to 6.1, and symfony contracts v3.1.
* Changed the used ElasticSearch DSL library to `shyim/opensearch-php-dsl`, instead of `ongr/elasticsearch-dsl`.
___ 
# Upgrade Information
## Update minimum PHP version to 8.1
Shopware 6 now requires at least PHP 8.1.0. Please update your PHP version to at least 8.1.0.
Refer to the upgrade guide to [v8.0](https://www.php.net/manual/en/migration80.php) and [v8.1](https://www.php.net/manual/en/migration81.php) for more information.
## Update to symfony 6.1
Shopware now uses symfony components in version 6.1, please make sure your plugins are compatible.
Refer to the upgrade guides to [v6.0](https://github.com/symfony/symfony/blob/6.2/UPGRADE-6.0.md) and [v6.1](https://github.com/symfony/symfony/blob/6.2/UPGRADE-6.1.md).
## Change Elasticsearch DSL library to `shyim/opensearch-php-dsl`
We changed the used Elasticsearch DSL library to `shyim/opensearch-php-dsl`, instead of `ongr/elasticsearch-dsl`.
It is a fork of the ONGR library and migrating should be straight forward. You need to change the namespace of the used classes from `ONGR\ElasticsearchDSL` to `OpenSearchDSL`.
Before:
```php
use ONGR\ElasticsearchDSL\Aggregation\AbstractAggregation;
```
After:
```php
use OpenSearchDSL\Aggregation\AbstractAggregation;
```
