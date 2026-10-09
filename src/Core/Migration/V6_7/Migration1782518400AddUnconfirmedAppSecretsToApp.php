<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1782518400AddUnconfirmedAppSecretsToApp extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1782518400;
    }

    public function update(Connection $connection): void
    {
        // A JSON list of the uncommitted secrets the app might still hold — the in-flight rotation/install
        // secret at the head, plus any a prior ambiguous recovery left behind. Recovery signs with each in
        // turn; the list is cleared once a secret is committed.
        $this->addColumn($connection, 'app', 'unconfirmed_app_secrets', 'JSON');
    }
}
