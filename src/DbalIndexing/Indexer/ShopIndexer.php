<?php declare(strict_types=1);

namespace Shopware\DbalIndexing\Indexer;

use Shopware\Api\Write\GenericWrittenEvent;

class ShopIndexer implements IndexerInterface
{
    /**
     * @var IndexerInterface[]
     */
    private $indexer;

    public function __construct(iterable $indexer)
    {
        $this->indexer = $indexer;
    }

    public function index(\DateTime $timestamp): void
    {
        foreach ($this->indexer as $indexer) {
            $indexer->index($timestamp);
        }
    }

    public function refresh(GenericWrittenEvent $event): void
    {
        foreach ($this->indexer as $indexer) {
            $indexer->refresh($event);
        }
    }
}
