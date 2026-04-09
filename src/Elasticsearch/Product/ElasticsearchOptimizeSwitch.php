<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use Shopware\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Framework\Indexing\Event\ElasticsearchIndexingFinishedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 *
 * @deprecated tag:v6.8.0 - reason:remove-subscriber - will be removed without alternative
 */
#[Package('inventory')]
class ElasticsearchOptimizeSwitch implements EventSubscriberInterface
{
    /**
     * @deprecated tag:v6.8.0 - reason:remove-subscriber - will be removed, this app_config value will be always true
     */
    public const FLAG = 'ELASTIC_OPTIMIZE_FLAG';

    /**
     * @internal
     */
    public function __construct(private readonly AbstractKeyValueStorage $storage)
    {
    }

    /**
     * @deprecated tag:v6.8.0 - reason:remove-subscriber - will be removed without alternative
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ElasticsearchIndexingFinishedEvent::class => 'onIndexingFinished',
        ];
    }

    /**
     * @deprecated tag:v6.8.0 - reason:remove-subscriber - will be removed without alternative
     */
    public function onIndexingFinished(ElasticsearchIndexingFinishedEvent $event): void
    {
        $this->storage->set(self::FLAG, true);
    }
}
