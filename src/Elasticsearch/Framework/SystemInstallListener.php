<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework;

use Shopware\Core\Framework\Event\SystemInstallCompletedEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexer;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * @internal
 */
#[Package('framework')]
#[AsEventListener]
class SystemInstallListener
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ElasticsearchIndexer $indexer,
    ) {
    }

    public function __invoke(SystemInstallCompletedEvent $event): void
    {
        try {
            $this->indexer->createIndices();
        } catch (\Throwable) {
            // Unreachable or misconfigured Elasticsearch must not fail system:install
        }
    }
}
