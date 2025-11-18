<?php

declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class Migration1763316536ChangeProductManufacturerLink extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1763316536;
    }

    public function update(Connection $connection): void
    {
        if (
            $this->columnExists($connection, 'product_manufacturer', 'link')
            && !$this->columnExists($connection, 'product_manufacturer_translation', 'link')
        ) {
            $connection->executeStatement(
                <<<'SQL'

ALTER TABLE `product_manufacturer_translation`
ADD COLUMN `link` LONGTEXT COLLATE utf8mb4_unicode_ci NULL;

SQL
            );

            $connection->executeStatement(
                <<<'SQL'

UPDATE `product_manufacturer` AS pm
INNER JOIN `product_manufacturer_translation` AS pmt
    ON pm.id = pmt.product_manufacturer_id
   AND pm.version_id = pmt.product_manufacturer_version_id
SET pmt.`link` = pm.`link`
WHERE pm.`link` IS NOT NULL
  AND pmt.`language_id` = :languageId;

SQL,
                ['languageId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)],
                ['languageId' => ParameterType::BINARY]
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        $this->dropColumnIfExists($connection, 'product_manufacturer', 'link');
    }
}
