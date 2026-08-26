<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1780645634AddProductDescriptionTeaser extends MigrationStep
{
    private const PARTIAL_DATA_LOADING_CONFIG_KEY = 'core.listing.partialDataLoading';

    public function getCreationTimestamp(): int
    {
        return 1780645634;
    }

    public function update(Connection $connection): void
    {
        $this->enablePartialDataLoadingForNewInstallations($connection);

        if ($this->columnExists($connection, 'product_translation', 'description_teaser')) {
            return;
        }

        $this->addColumn($connection, 'product_translation', 'description_teaser', 'VARCHAR(512)');

        /**
         * Backfilling synchronously does not scale (a one-update-per-row loop already exceeds the
         * 10s migration budget at ~50k translation rows), so existing rows are reconciled
         * asynchronously by the registered indexer after the update.
         */
        $this->registerIndexer($connection, 'product.description_teaser.indexer');
    }

    /**
     * Reduced listing loading is opt-in for existing shops (themes/extensions may rely on full
     * product data), but enabled from the start for fresh installations.
     */
    private function enablePartialDataLoadingForNewInstallations(Connection $connection): void
    {
        if (!$this->isInstallation()) {
            return;
        }

        $exists = $connection->fetchOne(
            <<<'SQL'
            SELECT 1
            FROM `system_config`
            WHERE `configuration_key` = :key AND `sales_channel_id` IS NULL
            SQL,
            ['key' => self::PARTIAL_DATA_LOADING_CONFIG_KEY]
        );

        if ($exists) {
            return;
        }

        $connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => self::PARTIAL_DATA_LOADING_CONFIG_KEY,
            'configuration_value' => json_encode(['_value' => true], \JSON_THROW_ON_ERROR),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }
}
