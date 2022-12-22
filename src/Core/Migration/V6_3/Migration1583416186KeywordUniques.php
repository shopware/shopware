<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @package core
 *
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Migrations will be internal in v6.5.0
 */
class Migration1583416186KeywordUniques extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1583416186;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('TRUNCATE product_keyword_dictionary');

        $connection->executeStatement('ALTER TABLE `product_keyword_dictionary` ADD UNIQUE `uniq.language_id_keyword` (`language_id`, `keyword`);');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
