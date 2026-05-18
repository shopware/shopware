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
class Migration1763125891AddProductTypeColumn extends MigrationStep
{
    use RelaxesNonStandardFkGuardTrait;

    public function getCreationTimestamp(): int
    {
        return 1763125891;
    }

    public function update(Connection $connection): void
    {
        // ALTER TABLE on `product` can fail on MySQL 8.4 if a child table holds
        // a non-standard FK against it — see issue #16240 / MySQL bug #118151.
        // The trait relaxes `restrict_fk_on_non_standard_key` for this session
        // only; on MariaDB / MySQL <8.4 it is a no-op.
        $this->runWithRelaxedNonStandardFkGuard($connection, function (Connection $connection): void {
            if (!TableHelper::columnExists($connection, 'product', 'type')) {
                $this->addColumn(
                    $connection,
                    'product',
                    'type',
                    'VARCHAR(32)',
                    false,
                    '\'physical\''
                );

                $connection->executeStatement('CREATE INDEX `idx.product.type` ON `product` (`type`)');
            }

            if (!TableHelper::indexExists($connection, 'product', 'idx.product.type')) {
                $connection->executeStatement('CREATE INDEX `idx.product.type` ON `product` (`type`)');
            }

            $batchSize = 5000;

            do {
                $affected = $connection->executeStatement(
                    "UPDATE `product`
                     SET `product`.`type` = 'digital'
                     WHERE `type` <> 'digital' AND JSON_CONTAINS(states, '\"is-download\"')
                     ORDER BY `id`
                     LIMIT {$batchSize};"
                );
            } while ($affected > 0);
        });
    }
}
