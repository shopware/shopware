<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1787216476AddDocumentNumberTypeNameUniqueIndex extends MigrationStep
{
    final public const INDEX_NAME = 'uniq.document.document_number__type_name';

    public function getCreationTimestamp(): int
    {
        return 1787216476;
    }

    public function update(Connection $connection): void
    {
        if ($this->indexExists($connection, 'document', self::INDEX_NAME)) {
            return;
        }

        $connection->executeStatement(
            'UPDATE `document` AS `d`
             INNER JOIN `document_type` AS `dt` ON `dt`.`id` = `d`.`document_type_id`
             SET `d`.`type_name` = `dt`.`technical_name`
             WHERE `d`.`type_name` IS NULL'
        );

        $hasDuplicates = $connection->fetchOne(
            'SELECT 1 FROM `document`
             WHERE `document_number` IS NOT NULL AND `type_name` IS NOT NULL
             GROUP BY `document_number`, `type_name`
             HAVING COUNT(*) > 1
             LIMIT 1'
        );

        // No unique index existed before this migration, so a shop can carry pre-existing duplicate
        // (document_number, type_name) rows. Adding the index would abort the whole update, so we skip it instead.
        if ($hasDuplicates !== false) {
            return;
        }

        $connection->executeStatement(
            'ALTER TABLE `document` ADD UNIQUE INDEX `uniq.document.document_number__type_name` (`document_number`, `type_name`)'
        );
    }
}
