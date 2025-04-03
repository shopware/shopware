<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1743676971AddressHouseNumber extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1743676971;
    }

    public function update(Connection $connection): void
    {
        if (!$this->columnExists($connection, 'customer_address', 'house_number')) {
            $this->addColumn($connection, 'customer_address', 'house_number', 'VARCHAR(30)', true);
        }

        if (!$this->columnExists($connection, 'order_address', 'house_number')) {
            $this->addColumn($connection, 'order_address', 'house_number', 'VARCHAR(30)', true);
        }

        if (!$this->columnExists($connection, 'newsletter_recipient', 'house_number')) {
            $this->addColumn($connection, 'newsletter_recipient', 'house_number', 'VARCHAR(30)', true);
        }
    }
}
