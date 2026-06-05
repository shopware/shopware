<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1780645634AddProductDescriptionTeaser extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1780645634;
    }

    public function update(Connection $connection): void
    {
        if ($this->columnExists($connection, 'product_translation', 'description_teaser')) {
            return;
        }

        $sql = <<<'SQL'
            ALTER TABLE `product_translation`
            ADD COLUMN `description_teaser` VARCHAR(255)
                GENERATED ALWAYS AS (LEFT(REGEXP_REPLACE(LEFT(`description`, 2000), '<[^>]*>', ''), 255)) VIRTUAL
        SQL;

        try {
            $connection->executeStatement($sql . ', ALGORITHM=INSTANT;');
        } catch (DBALException) {
            // INSTANT is not supported for generated columns on all MySQL/MariaDB versions
            $connection->executeStatement($sql . ';');
        }
    }
}
