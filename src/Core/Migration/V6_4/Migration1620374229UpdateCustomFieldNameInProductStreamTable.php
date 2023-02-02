<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1620374229UpdateCustomFieldNameInProductStreamTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1620374229;
    }

    public function update(Connection $connection): void
    {
        $customFields = $connection->fetchFirstColumn('
            SELECT
                custom_field.`name`
            FROM custom_field_set_relation
                INNER JOIN custom_field ON(custom_field.set_id = custom_field_set_relation.set_id)
            WHERE custom_field_set_relation.entity_name = "product"
            ORDER BY custom_field.`name` ASC
        ');

        if (empty($customFields)) {
            return;
        }

        foreach ($customFields as $customField) {
            $oldField = 'product.' . $customField . '",';
            $updateField = 'product.customFields.' . $customField . '",';
            $connection->executeStatement('UPDATE product_stream_filter SET `field` = ? WHERE `field` = ?', ['customFields.' . $customField, $customField]);
            $connection->executeStatement("UPDATE product_stream SET `api_filter` = REPLACE(`api_filter`, '" . $oldField . "', '" . $updateField . "') WHERE `api_filter` LIKE '%" . $customField . "%'");
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
