<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1752823743ShippingMethodPriceQuantityStepColumns extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1752823743;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'shipping_method_price', 'quantity_step', 'DOUBLE');
        $this->addColumn($connection, 'shipping_method_price', 'quantity_step_price', 'JSON');

        $connection->executeStatement('
            ALTER TABLE `shipping_method_price`
            ADD CONSTRAINT `json.shipping_method_price.quantity_step_price`
            CHECK (JSON_VALID(`quantity_step_price`))
        ');
    }
}
