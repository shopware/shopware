<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1681382023AddCustomFieldAllowCartExpose extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1681382023;
    }

    public function update(Connection $connection): void
    {
        if (!EntityDefinitionQueryHelper::columnExists($connection, 'custom_field', 'allow_cart_expose')) {
            $connection->executeStatement('ALTER TABLE `custom_field` ADD `allow_cart_expose` tinyint default 0 NOT NULL');
        }

        $customFieldAllowList = $connection->fetchFirstColumn('SELECT JSON_UNQUOTE(JSON_EXTRACT(`value`, "$.renderedField.name")) as technical_name FROM rule_condition WHERE type = \'cartLineItemCustomField\';');

        foreach ($customFieldAllowList as $customField) {
            $connection->update(
                'custom_field',
                ['allow_cart_expose' => '1'],
                ['name' => $customField]
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
