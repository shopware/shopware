<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1786365555AddUnconfirmedSecretsToDeletedApps extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1786365555;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'deleted_apps', 'unconfirmed_app_secrets', 'JSON');
    }
}
