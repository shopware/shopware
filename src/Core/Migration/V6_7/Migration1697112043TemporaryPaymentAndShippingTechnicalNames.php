<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1697112043TemporaryPaymentAndShippingTechnicalNames extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1697112043;
    }

    public function update(Connection $connection): void
    {
        $this->addAppShippingMethodTechnicalNames($connection);
        $this->addAppPaymentMethodTechnicalNames($connection);

        $this->addTemporaryPaymentMethodTechnicalNames($connection);
        $this->addTemporaryShippingMethodTechnicalNames($connection);
    }

    private function addTemporaryPaymentMethodTechnicalNames(Connection $connection): void
    {
        $connection->executeStatement('
            UPDATE `payment_method`
            SET `payment_method`.`technical_name` = CONCAT(\'temporary_\', LOWER(HEX(`payment_method`.`id`)))
            WHERE `payment_method`.`technical_name` IS NULL;
        ');
    }

    private function addTemporaryShippingMethodTechnicalNames(Connection $connection): void
    {
        $connection->executeStatement('
            UPDATE `shipping_method`
            SET `shipping_method`.`technical_name` = CONCAT(\'temporary_\', LOWER(HEX(`shipping_method`.`id`)))
            WHERE `shipping_method`.`technical_name` IS NULL;
        ');
    }

    private function addAppShippingMethodTechnicalNames(Connection $connection): void
    {
        $connection->executeStatement('
            UPDATE IGNORE `shipping_method`
            RIGHT JOIN `app_shipping_method` ON `app_shipping_method`.`shipping_method_id` = `shipping_method`.`id`
            SET `shipping_method`.`technical_name` = CONCAT(\'shipping_\', `app_shipping_method`.`app_name`, \'_\', `app_shipping_method`.`identifier`)
            WHERE `shipping_method`.`technical_name` IS NULL;
        ');
    }

    private function addAppPaymentMethodTechnicalNames(Connection $connection): void
    {
        $connection->executeStatement('
            UPDATE IGNORE `payment_method`
            RIGHT JOIN `app_payment_method` ON `app_payment_method`.`payment_method_id` = `payment_method`.`id`
            SET `payment_method`.`technical_name` = CONCAT(\'payment_\', LOWER(SUBSTRING_INDEX(`payment_method`.`handler_identifier`, \'\\\\\', -1)))
            WHERE `payment_method`.`technical_name` IS NULL;
        ');
    }
}
