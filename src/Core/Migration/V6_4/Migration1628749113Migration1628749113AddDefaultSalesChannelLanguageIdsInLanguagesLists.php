<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1628749113Migration1628749113AddDefaultSalesChannelLanguageIdsInLanguagesLists extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1628749113;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            INSERT INTO sales_channel_language (sales_channel_id, language_id)
            SELECT sc.id, sc.language_id
            FROM sales_channel sc
            LEFT JOIN sales_channel_language scl
            ON sc.id = scl.sales_channel_id
            WHERE scl.language_id IS NULL
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
