<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('data-services')]
class Migration1772443355InitiallySetRevokedConsentsToDeclined extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1772443355;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(' UPDATE `consent_state` SET `state` = "declined" WHERE `state` = "revoked"');
    }
}
