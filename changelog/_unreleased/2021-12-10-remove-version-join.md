---
title: Remove version join, to improve read performance of versioned entities
issue: NEXT-19241
---
# Core
* Changed `\Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper::joinVersion()` to not perform a version join, but rather filter the correct version through a `WHERE` clause, thus improving the performance on large datasets.
