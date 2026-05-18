<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\Traits\RelaxesNonStandardFkGuardTrait;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1756305375AddCategoriesIndexToProduct extends MigrationStep
{
    use RelaxesNonStandardFkGuardTrait;

    public function getCreationTimestamp(): int
    {
        return 1756305375;
    }

    public function update(Connection $connection): void
    {
        if (TableHelper::indexExists($connection, 'product', 'idx.product.categories')) {
            return;
        }

        // CREATE INDEX on `product` can fail on MySQL 8.4 if a child table holds
        // a non-standard FK against it — see issue #13039 / MySQL bug #118151.
        // The trait relaxes `restrict_fk_on_non_standard_key` for this session
        // only; on MariaDB / MySQL <8.4 it is a no-op.
        $this->runWithRelaxedNonStandardFkGuard($connection, function (Connection $connection): void {
            $connection->executeStatement('CREATE INDEX `idx.product.categories` ON `product` (`categories`)');
        });
    }
}
