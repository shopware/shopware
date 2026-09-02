<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('after-sales')]
class Migration1787130172BackfillDocumentTypeName extends MigrationStep
{
    final public const DOCUMENT_TYPE_TABLES = [
        'document',
        'document_base_config',
        'document_base_config_sales_channel',
    ];

    private const UPDATE_LIMIT = 1000;

    public function getCreationTimestamp(): int
    {
        return 1787130172;
    }

    public function update(Connection $connection): void
    {
        foreach (self::DOCUMENT_TYPE_TABLES as $table) {
            if (TableHelper::columnExists($connection, $table, 'type_name')) {
                $this->backfillTable($connection, $table);
            }
        }
    }

    private function backfillTable(Connection $connection, string $table): void
    {
        do {
            $ids = $connection->fetchFirstColumn(
                \sprintf(
                    'SELECT `id` FROM `%s` WHERE `type_name` IS NULL AND `document_type_id` IS NOT NULL LIMIT :limit',
                    $table
                ),
                ['limit' => self::UPDATE_LIMIT],
                ['limit' => ParameterType::INTEGER]
            );

            if ($ids === []) {
                break;
            }

            $connection->executeStatement(
                \sprintf(
                    'UPDATE `%s` AS `target`
                    INNER JOIN `document_type` AS `type` ON `type`.`id` = `target`.`document_type_id`
                    SET `target`.`type_name` = `type`.`technical_name`
                    WHERE `target`.`id` IN (:ids) AND `target`.`type_name` IS NULL',
                    $table
                ),
                ['ids' => $ids],
                ['ids' => ArrayParameterType::BINARY]
            );
        } while (\count($ids) === self::UPDATE_LIMIT);
    }
}
