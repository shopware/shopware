<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1764064757SetSearchableForExistingCustomFieldsInProductSearch extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1764064757;
    }

    public function update(Connection $connection): void
    {
        $customFieldIds = $connection->executeQuery('
            SELECT DISTINCT custom_field_id
            FROM product_search_config_field
            WHERE custom_field_id IS NOT NULL
                AND searchable = 1
        ')->fetchFirstColumn();

        if ($customFieldIds === []) {
            return;
        }

        $connection->executeStatement(
            'UPDATE custom_field SET include_in_search = 1 WHERE id IN (:ids)',
            ['ids' => $customFieldIds],
            ['ids' => ArrayParameterType::BINARY]
        );
    }
}
