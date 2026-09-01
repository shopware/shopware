<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration\Reversible;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;

/**
 * Passed to Migration::down().
 */
#[Package('framework')]
final readonly class DownMigrationContext
{
    public function __construct(
        public Connection $connection,
        public bool $keepUserData,
    ) {
    }
}
