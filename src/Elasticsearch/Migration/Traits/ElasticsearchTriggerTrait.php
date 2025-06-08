<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Migration\Traits;

use Doctrine\DBAL\Connection;
use Shopware\Elasticsearch\Framework\SystemUpdateListener;

trait ElasticsearchTriggerTrait
{
    /**
     * This method triggers Elasticsearch indexing after Shopware Update
     */
    public function triggerElasticsearchIndexing(Connection $connection): void
    {
        $connection->executeStatement(
            '
            REPLACE INTO app_config (`key`, `value`) VALUES
            (?, ?)
            ',
            [SystemUpdateListener::CONFIG_KEY, json_encode(['*'], \JSON_THROW_ON_ERROR)]
        );
    }
}
