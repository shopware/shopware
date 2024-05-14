---
title: ES admin search is broken with third party plugins
issue: NEXT-31769
---
# Core
* Changed method `search` in `src/Elasticsearch/Admin/AdminSearcher.php` to catch `ElasticsearchException` thrown when indexer not found
* Changed methods `buildSearch` in `src/Elasticsearch/Admin/AdminSearcher.php` and `globalCriteria` in `src/Elasticsearch/Admin/Indexer/ProductAdminSearchIndexer.php` to allow search with Umlauts
