<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\System\Country\CountryDefinition;

/**
 * @internal
 */
#[Package('core')]
class Migration1658786605AddAddressFormatIntoCountryTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1658786605;
    }

    public function update(Connection $connection): void
    {
        $field = $connection->fetchOne(
            'SHOW COLUMNS FROM `country_translation` WHERE `Field` LIKE :column;',
            ['column' => 'address_format']
        );

        if (!empty($field)) {
            return;
        }

        $addressFormat = json_encode(CountryDefinition::DEFAULT_ADDRESS_FORMAT);

        $connection->executeStatement('
            ALTER TABLE `country_translation` ADD COLUMN `address_format` JSON NULL AFTER `name`;
        ');

        $connection->update('country_translation', [
            'address_format' => $addressFormat,
        ], ['address_format' => null]);

        $connection->executeStatement(
            'ALTER TABLE `country_translation` MODIFY `address_format` JSON NOT NULL;'
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
