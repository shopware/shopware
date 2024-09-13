<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1718658881AddValidationDataToOrderTransaction extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1718658881;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'order_transaction', 'validation_data', 'JSON NULL');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
