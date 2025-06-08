---
title: Implement new admin listing via elasticsearch
issue: NEXT-21731
---
# API
* Added new API `POST: /api/_admin/es-search` at `src/Elasticsearch/Admin/AdminSearchController.php` to search by ES
* Changed method `index` in `Shopware\Administration\Controller\AdministrationController` to set enable admin ES
___
# Core
* Added class `src/Elasticsearch/Admin/AdminElasticsearchHelper.php`
* Added class `src/Elasticsearch/Admin/Indexer/AbstractAdminIndexer.php`
* Added class `src/Elasticsearch/Admin/Indexer/CmsPageAdminSearchIndexer.php` to get data for indexing cms_page
* Added class `src/Elasticsearch/Admin/Indexer/CustomerAdminSearchIndexer.php` to get data for indexing customer
* Added class `src/Elasticsearch/Admin/Indexer/CustomerGroupAdminSearchIndexer.php` to get data for indexing customer_group
* Added class `src/Elasticsearch/Admin/Indexer/LandingPageAdminSearchIndexer.php` to get data for indexing landing_page
* Added class `src/Elasticsearch/Admin/Indexer/ManufacturerAdminSearchIndexer.php` to get data for indexing manufacturer
* Added class `src/Elasticsearch/Admin/Indexer/MediaAdminSearchIndexer.php` to get data for indexing media
* Added class `src/Elasticsearch/Admin/Indexer/OrderAdminSearchIndexer.php` to get data for indexing order
* Added class `src/Elasticsearch/Admin/Indexer/PaymentMethodAdminSearchIndexer.php` to get data for indexing payment_method
* Added class `src/Elasticsearch/Admin/Indexer/ProductAdminSearchIndexer.php` to get data for indexing product
* Added class `src/Elasticsearch/Admin/Indexer/PromotionAdminSearchIndexer.php` to get data for indexing promotion
* Added class `src/Elasticsearch/Admin/Indexer/PropertyGroupAdminSearchIndexer.php` to get data for indexing property_group
* Added class `src/Elasticsearch/Admin/Indexer/SalesChannelAdminSearchIndexer.php` to get data for indexing sales_channel
* Added class `src/Elasticsearch/Admin/Indexer/ShippingMethodAdminSearchIndexer.php` to get data for indexing shipping_method
* Added class `src/Elasticsearch/Admin/Indexer/CategoryAdminSearchIndexer.php` to get data for indexing category
* Added class `src/Elasticsearch/Admin/Indexer/NewsletterRecipientAdminSearchIndexer.php` to get data for indexing newsletter_recipient
* Added class `src/Elasticsearch/Admin/Indexer/ProductStreamAdminSearchIndexer.php` to get data for indexing product_stream
* Added class `src/Elasticsearch/Admin/AdminSearchIndexingMessage.php` to get data for indexing cms_page
* Added class `src/Elasticsearch/Admin/AdminSearchRegistry.php` to handle create indices and push data to ES
* Added class `src/Elasticsearch/Admin/AdminSearcher.php` to handle searching by ES
* Added class `src/Elasticsearch/Framework/Command/ElasticsearchAdminIndexingCommand.php`
* Added class `src/Elasticsearch/Framework/Command/ElasticsearchAdminResetCommand.php` to reset index
* Changed class `src/Core/Framework/DataAbstractionLayer/Command/RefreshIndexCommand.php` to refresh index for Admin ES
* Added `src/Core/Migration/V6_4/Migration1667731399AdminElasticsearchIndexTask.php`
___
# Administration
* Changed method `loadResults` in `src/app/component/structure/sw-search-bar/index.js` to allow search by ES
* Added method `elastic` in `src/core/service/api/search.api.service.js` to call API searching by ES
