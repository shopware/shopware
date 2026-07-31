<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1785414800MakeDocumentTypeIdNullable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1785414800;
    }

    public function update(Connection $connection): void
    {
        /** @var array{Null: string}|false $column */
        $column = $connection->fetchAssociative(
            'SHOW COLUMNS FROM `document` WHERE `Field` = :field',
            ['field' => 'document_type_id'],
        );

        if ($column === false || $column['Null'] === 'YES') {
            return;
        }

        $connection->executeStatement(
            'ALTER TABLE `document` MODIFY `document_type_id` BINARY(16) NULL'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
