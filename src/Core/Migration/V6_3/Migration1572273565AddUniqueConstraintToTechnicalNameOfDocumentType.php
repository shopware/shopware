<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Adds a missing unique constraint to column `technical_name` of table `document_type`.
 * Before that, it removes rows with duplicated `technical_name` from table `document_type`
 *
 * @internal
 */
#[Package('core')]
class Migration1572273565AddUniqueConstraintToTechnicalNameOfDocumentType extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1572273565;
    }

    public function update(Connection $connection): void
    {
        $duplicatedDocumentTypes = $connection->fetchFirstColumn(
            'SELECT `id`
            FROM `document_type`
            WHERE (`technical_name`, `created_at`) NOT IN (
                SELECT
                    `technical_name`,
                    MIN(`created_at`)
                FROM `document_type`
                GROUP BY `technical_name`
            )'
        );

        foreach ($duplicatedDocumentTypes as $duplicatedDocumentType) {
            $connection->executeStatement(
                'DELETE FROM `document_type`
                WHERE `id` = :id',
                $duplicatedDocumentType
            );
        }

        $connection->executeStatement(
            'ALTER TABLE `document_type` ADD CONSTRAINT `uniq.document_type.technical_name` UNIQUE (`technical_name`)'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
