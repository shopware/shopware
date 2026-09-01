<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration\Reversible;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;

/**
 * Passed to Migration::up().
 *
 * Note that $isInstallation has a different meaning than MigrationStep::isInstallation(): it does not
 * describe whether Shopware itself is being installed, but whether this plugin is being installed for
 * the first time. Use it to skip backfills that cannot have anything to backfill.
 */
#[Package('framework')]
final readonly class UpMigrationContext
{
    public function __construct(
        public Connection $connection,
        public bool $isInstallation,
    ) {
    }
}
