---
title: Fix admin es search for document number does not return any result
issue: 11074
---
# Core
* Changed `fetch` method in `Shopware\Elasticsearch\Admin\Indexer\OrderAdminSearchIndexer` to fetch all document numbers for orders.
