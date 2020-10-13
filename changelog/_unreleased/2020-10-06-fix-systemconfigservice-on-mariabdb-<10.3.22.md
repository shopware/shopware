---
title: Fix `SystemConfigService` on MariaDB <10.3.22
issue: NEXT-11244
---
# Core
* Changed `Shopware\Core\System\SystemConfig\SystemConfigService` which now uses `Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator` to prevent issue when fetching more that ~1000 rows, resulting in an empty collection without any error, on some database systems like MariaDB 10.3.21. The data is now fetched in chunks.
