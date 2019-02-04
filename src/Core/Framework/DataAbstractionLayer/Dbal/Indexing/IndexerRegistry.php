<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\Indexing;

use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class IndexerRegistry implements IndexerInterface, EventSubscriberInterface
{
    /**
     * 0    => Shopware\Core\Content\ProductStream\DataAbstractionLayer\Indexing\ProductStreamIndexer
     * 0    => Shopware\Core\Content\Rule\DataAbstractionLayer\Indexing\RulePayloadIndexer
     * 100  => Shopware\Core\Content\Navigation\DataAbstractionLayer\NavigationSynchronizer
     * 200  => Shopware\Core\Framework\Search\DataAbstractionLayer\Indexing\SearchKeywordIndexer
     * 300  => Shopware\Core\Content\Product\DataAbstractionLayer\Indexing\ProductListingPriceIndexer
     * 400  => Shopware\Core\Content\Product\DataAbstractionLayer\Indexing\ProductDatasheetIndexer
     * 500  => Shopware\Core\Content\Product\DataAbstractionLayer\Indexing\ProductCategoryTreeIndexer
     * 500  => Shopware\Core\Content\Media\DataAbstractionLayer\Indexing\MediaFolderConfigIndexer
     * 900  => Shopware\Core\Content\Product\DataAbstractionLayer\Indexing\ProductVariationIndexer
     * 1000 => Shopware\Core\Framework\DataAbstractionLayer\Dbal\Indexing\ChildCountIndexer
     * 1000 => Shopware\Core\Framework\DataAbstractionLayer\Dbal\Indexing\TreeIndexer
     * 1500 => Shopware\Core\Framework\DataAbstractionLayer\Dbal\Indexing\InheritanceIndexer
     *
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
            EntityWrittenContainerEvent::NAME => [
                ['refresh', 500],
            ],
        ];
    }

    public function index(\DateTime $timestamp): void
    {
        foreach ($this->indexer as $indexer) {
            $indexer->index($timestamp);
        }
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        foreach ($this->indexer as $indexer) {
            $indexer->refresh($event);
        }
    }
}
