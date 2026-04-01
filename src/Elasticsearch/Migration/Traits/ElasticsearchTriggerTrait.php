<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Migration\Traits;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Feature;
use Shopware\Elasticsearch\Framework\SystemUpdateListener;

/**
 * @deprecated tag:v6.8.0 - Will be removed as it unused
 *
 * @phpstan-ignore trait.unused
 */
trait ElasticsearchTriggerTrait
{
    /**
     * This method triggers Elasticsearch indexing after Shopware Update
     */
    public function triggerElasticsearchIndexing(Connection $connection): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0')
        );

        $connection->executeStatement(
            '
            REPLACE INTO app_config (`key`, `value`) VALUES
            (?, ?)
            ',
            [SystemUpdateListener::CONFIG_KEY, json_encode(['*'], \JSON_THROW_ON_ERROR)]
        );
    }
}
