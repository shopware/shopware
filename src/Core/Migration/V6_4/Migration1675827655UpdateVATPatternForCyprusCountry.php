<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('customer-order')]
class Migration1675827655UpdateVATPatternForCyprusCountry extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1675827655;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'UPDATE country SET vat_id_pattern = :pattern WHERE iso = :iso;',
            ['pattern' => '(CY)?[0-9]{8}[A-Z]{1}', 'iso' => 'CY']
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
