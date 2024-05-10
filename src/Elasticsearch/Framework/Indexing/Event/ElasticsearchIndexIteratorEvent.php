<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Indexing\Event;

use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;

/**
 * @codeCoverageIgnore
 */
#[Package('core')]
class ElasticsearchIndexIteratorEvent
{
    public function __construct(
        public readonly AbstractElasticsearchDefinition $elasticsearchDefinition,
        public IterableQuery $iterator,
    ) {
    }
}
