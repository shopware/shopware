<?php

namespace Shopware\DbalIndexing\Indexer;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEventCollection;

class ShopIndexer implements IndexerInterface
{
    /**
     * @var IndexerInterface[]
     */
    private $indexer;

    public function __construct(array $indexer)
    {
        $this->indexer = $indexer;
    }

    public function index(TranslationContext $context, \DateTime $timestamp): void
    {
        foreach ($this->indexer as $indexer) {
            $indexer->index($context, $timestamp);
        }
    }

    public function refresh(NestedEventCollection $events, TranslationContext $context): void
    {
        foreach ($this->indexer as $indexer) {
            $indexer->refresh($events, $context);
        }
    }
}
