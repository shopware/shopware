<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\AddColumnTrait;
use Shopware\Core\Framework\Migration\ColumnExistsTrait;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Adds the `is_external_storefront` flag to `sales_channel_domain`. SEO URLs for headless sales channels are
 * only generated for domains flagged as external storefront. Defaults to `0` (false).
 *
 * Also enforces on database level that per language only a single domain of a sales channel can be marked as
 * external storefront. As MySQL/MariaDB do not support partial unique indexes, a virtual generated column is
 * used that only holds the language id for external storefront domains (and `NULL` otherwise). Unique indexes
 * ignore `NULL` values, so uniqueness is only enforced for external storefront domains.
 *
 * The generated column is intentionally the leftmost column of the unique index, so InnoDB does not adopt the
 * index as the supporting index for the `sales_channel_id` foreign key.
 *
 * @internal
 */
#[Package('discovery')]
class Migration1784801509AddIsExternalStorefrontToSalesChannelDomain extends MigrationStep
{
    use AddColumnTrait;
    use ColumnExistsTrait;

    public function getCreationTimestamp(): int
    {
        return 1784801509;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'sales_channel_domain', 'is_external_storefront', 'TINYINT(1)', false, '0');

        if (!$this->columnExists($connection, 'sales_channel_domain', 'external_storefront_language_id')) {
            $connection->executeStatement(
                'ALTER TABLE `sales_channel_domain`
                    ADD COLUMN `external_storefront_language_id` BINARY(16)
                        GENERATED ALWAYS AS (IF(`is_external_storefront` = 1, `language_id`, NULL)) VIRTUAL'
            );
        }

        if (!$this->indexExists($connection, 'sales_channel_domain', 'uniq.sales_channel_domain.external_storefront')) {
            $connection->executeStatement(
                'ALTER TABLE `sales_channel_domain`
                    ADD CONSTRAINT `uniq.sales_channel_domain.external_storefront`
                        UNIQUE (`external_storefront_language_id`, `sales_channel_id`)'
            );
        }
    }
}
