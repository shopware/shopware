<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\DbTableHelper;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1697112044PaymentAndShippingTechnicalNameRequired extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1697112044;
    }

    public function update(Connection $connection): void
    {
        if (DbTableHelper::columnExists($connection, PaymentMethodDefinition::ENTITY_NAME, 'technical_name')
            && !DbTableHelper::getColumnOfTable($connection, PaymentMethodDefinition::ENTITY_NAME, 'technical_name')->isNotNull
        ) {
            $connection->executeStatement('ALTER TABLE `payment_method` MODIFY COLUMN `technical_name` VARCHAR(255) NOT NULL');
        }

        if (DbTableHelper::columnExists($connection, ShippingMethodDefinition::ENTITY_NAME, 'technical_name')
            && !DbTableHelper::getColumnOfTable($connection, ShippingMethodDefinition::ENTITY_NAME, 'technical_name')->isNotNull
        ) {
            $connection->executeStatement('ALTER TABLE `shipping_method` MODIFY COLUMN `technical_name` VARCHAR(255) NOT NULL');
        }
    }
}
