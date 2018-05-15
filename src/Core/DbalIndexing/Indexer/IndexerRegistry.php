<?php declare(strict_types=1);

namespace Shopware\DbalIndexing\Indexer;

use Shopware\Framework\ORM\Write\GenericWrittenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class IndexerRegistry implements IndexerInterface, EventSubscriberInterface
{
    /**
     * @var IndexerInterface[]
     */
    private $indexer;

    public function __construct(iterable $indexer)
    {
        $this->indexer = $indexer;
    }

    public static function getSubscribedEvents()
    {
        return [
            GenericWrittenEvent::NAME => 'refresh',
        ];
    }

    public function index(\DateTime $timestamp, string $tenantId): void
    {
        foreach ($this->indexer as $indexer) {
            $indexer->index($timestamp, $tenantId);
        }
    }

    public function refresh(GenericWrittenEvent $event): void
    {
        foreach ($this->indexer as $indexer) {
            $indexer->refresh($event);
        }
    }
}
