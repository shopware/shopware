<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1611817467ChangeDefaultProductSettingConfigField extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1611817467;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('UPDATE product_search_config SET and_logic = 1');

        $connection->executeStatement('UPDATE product_search_config_field SET searchable = 1, tokenize = 1 WHERE field = :fieldName', [
            'fieldName' => 'name',
        ]);

        $connection->executeStatement('UPDATE product_search_config_field SET searchable = 1 WHERE field IN (:fieldsName)', [
            'fieldsName' => ['productNumber', 'ean', 'customSearchKeywords', 'manufacturer.name', 'manufacturerNumber'],
        ], ['fieldsName' => ArrayParameterType::STRING,
        ]);

        $connection->executeStatement('UPDATE product_search_config_field SET field = :newName where field = :oldName', [
            'newName' => 'options.name',
            'oldName' => 'variantRestrictions',
        ]);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
