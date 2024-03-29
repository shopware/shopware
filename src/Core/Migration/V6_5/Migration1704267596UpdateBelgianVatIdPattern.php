<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1704267596UpdateBelgianVatIdPattern extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1704267596;
    }

    public function update(Connection $connection): void
    {
        $connection->update('country', ['vat_id_pattern' => '(BE)?(0|1)[0-9]{9}'], ['vat_id_pattern' => '(BE)?0[0-9]{9}']);
    }
}
