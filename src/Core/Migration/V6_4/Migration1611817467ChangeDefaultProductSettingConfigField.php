<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1611817467ChangeDefaultProductSettingConfigField extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1611817467;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('UPDATE product_search_config SET and_logic = 1');

        $connection->executeUpdate('UPDATE product_search_config_field SET searchable = 1, tokenize = 1 WHERE field = :fieldName', [
            'fieldName' => 'name',
        ]);

        $connection->executeUpdate('UPDATE product_search_config_field SET searchable = 1 WHERE field IN (:fieldsName)', [
            'fieldsName' => ['productNumber', 'ean', 'customSearchKeywords', 'manufacturer.name', 'manufacturerNumber'],
        ], ['fieldsName' => Connection::PARAM_STR_ARRAY,
        ]);

        $connection->executeUpdate('UPDATE product_search_config_field SET field = :newName where field = :oldName', [
            'newName' => 'options.name',
            'oldName' => 'variantRestrictions',
        ]);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
