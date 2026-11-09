<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_8;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPriceBlobPostUpdater;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationException;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1794234846RemoveLegacyCheapestPrice extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1794234846;
    }

    public function update(Connection $connection): void
    {
        $connection->delete('system_config', ['configuration_key' => CheapestPriceBlobPostUpdater::COMPLETED_CONFIG_KEY]);
        $this->registerIndexer($connection, CheapestPriceBlobPostUpdater::NAME);
    }

    public function updateDestructive(Connection $connection): void
    {
        if (!$this->columnExists($connection, 'product', 'cheapest_price')) {
            return;
        }

        $completed = $connection->fetchOne(
            'SELECT JSON_UNQUOTE(JSON_EXTRACT(configuration_value, \'$._value\')) FROM system_config
             WHERE configuration_key = :key AND sales_channel_id IS NULL',
            ['key' => CheapestPriceBlobPostUpdater::COMPLETED_CONFIG_KEY]
        );

        if ($completed !== 'true' && $connection->fetchOne('SELECT 1 FROM product LIMIT 1')) {
            /** @phpstan-ignore shopware.domainException (Versioned migrations use the framework migration exception.) */
            throw MigrationException::migrationError('Complete the cheapest-price blob reconciliation with system:update:finish before removing product.cheapest_price. Then retry the destructive migration.');
        }

        $this->dropColumnIfExists($connection, 'product', 'cheapest_price');
    }
}
