<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Migration\Traits\RelaxesNonStandardFkGuardTrait;

/**
 * This migration repairs the FK of canonical_product_id to include the version_id. To fix MySQL 8.4 compatibility
 *
 * @internal
 */
#[Package('framework')]
class Migration1714659357CanonicalProductVersion extends MigrationStep
{
    use RelaxesNonStandardFkGuardTrait;

    public function getCreationTimestamp(): int
    {
        return 1714659357;
    }

    public function update(Connection $connection): void
    {
        // ALTER TABLE on `product` can fail on MySQL 8.4 if a child table holds
        // a non-standard FK against it — see issue #16240 / MySQL bug #118151.
        // The trait relaxes `restrict_fk_on_non_standard_key` for this session
        // only; on MariaDB / MySQL <8.4 it is a no-op.
        $this->runWithRelaxedNonStandardFkGuard($connection, function (Connection $connection): void {
            $this->addColumn($connection, 'product', 'canonical_product_version_id', 'binary(16)', true, '0x0fa91ce3e96a4bc2be4bd9ce752c3425');

            // The foreign key is dropped and immediately re-added below, so the
            // drop is safe. PHPStan's `shopware.dropStatement` rule does not
            // recurse into closures, so no ignore annotation is necessary here.
            $this->dropForeignKeyIfExists($connection, 'product', 'fk.product.canonical_product_id');
            $this->dropIndexIfExists($connection, 'product', 'fk.product.canonical_product_id');

            $connection->executeStatement('
                ALTER TABLE `product`
                ADD CONSTRAINT `fk.product.canonical_product_id`
                FOREIGN KEY (`canonical_product_id` , `canonical_product_version_id`)
                REFERENCES `product` (`id`, `version_id`)
                ON DELETE SET NULL
            ');
        });
    }
}
