<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1752662784ChangeCountryNameTuerkiye extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1752662784;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('UPDATE country_translation SET name = ? WHERE name = ?', [
            'Türkiye',
            'Turkey',
        ]);

        $connection->executeStatement('UPDATE country_translation SET name = ? WHERE name = ?', [
            'North Macedonia',
            'Macedonia (the former Yugoslav Republic of)',
        ]);
    }
}
