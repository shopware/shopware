<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1583416186KeywordUniques extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1583416186;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('TRUNCATE product_keyword_dictionary');

        $connection->executeUpdate('ALTER TABLE `product_keyword_dictionary` ADD UNIQUE `uniq.language_id_keyword` (`language_id`, `keyword`);');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
