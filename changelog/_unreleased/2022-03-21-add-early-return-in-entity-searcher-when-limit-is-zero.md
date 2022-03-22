---
title: Don't perform queries in `EntitySearcher` if criteria.limit = 0
issue: NEXT-20689
---
# Core
* Changed `\Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntitySearcher::search()` to not perform a query if `$criteria->getLimit() === 0`.
___
# Elasticsearch
* Changed `\Shopware\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchEntitySearcher::search()` to not perform a query if `$criteria->getLimit() === 0`.
