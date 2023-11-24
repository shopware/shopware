<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1700812454ChangeBelgianVATValidationRegex extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1700812454;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('UPDATE `country` SET `vat_id_pattern` = "(BE)?[01][0-9]{9}" WHERE `iso` = "BE"');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
