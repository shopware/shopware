<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1561442979ElasticsearchIndexTask extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1561442979;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
CREATE TABLE `elasticsearch_index_task` (
  `id` binary(16) NOT NULL,
  `index` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `alias` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `doc_count` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
