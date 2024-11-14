<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1729843379FixBelgianVatIdPattern extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1729843379;
    }

    public function update(Connection $connection): void
    {
        $connection->update('country', ['vat_id_pattern' => 'BE(0|1)\d{9}'], ['vat_id_pattern' => 'BE0\d{9}']);
    }
}
