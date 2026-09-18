<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1788531955MakeProductGuaranteeConfirmedInheritable extends MigrationStep
{
    private const UPDATE_LIMIT = 1000;

    public function getCreationTimestamp(): int
    {
        return 1788531955;
    }

    public function update(Connection $connection): void
    {
        if (!$this->columnExists($connection, 'product', 'guarantee_confirmed')) {
            return;
        }

        if ($this->isColumnNotNull($connection, 'product', 'guarantee_confirmed')) {
            $this->executeDdlStatement(
                $connection,
                'ALTER TABLE `product` MODIFY COLUMN `guarantee_confirmed` TINYINT(1) NULL DEFAULT NULL'
            );
        }

        do {
            $affectedRows = (int) $connection->executeStatement(
                'UPDATE `product`
                 SET `guarantee_confirmed` = NULL
                 WHERE `parent_id` IS NOT NULL AND `guarantee_confirmed` = 0
                 LIMIT :limit',
                ['limit' => self::UPDATE_LIMIT],
                ['limit' => ParameterType::INTEGER]
            );
        } while ($affectedRows === self::UPDATE_LIMIT);

        $this->registerIndexer($connection, 'product.indexer');
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function isColumnNotNull(Connection $connection, string $table, string $column): bool
    {
        $definition = $connection->fetchAssociative(
            'SHOW COLUMNS FROM `' . $table . '` WHERE `Field` = :column',
            ['column' => $column]
        );

        return $definition !== false && $definition['Null'] === 'NO';
    }
}
