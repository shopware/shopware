<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\DataAbstractionLayer\Indexing\ListingPriceUpdater;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\Uuid\Uuid;

class ProductIndexer implements EntityIndexerInterface
{
    /**
     * @var IteratorFactory
     */
    private $iteratorFactory;

    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var CacheClearer
     */
    private $cacheClearer;

    /**
     * @var VariantListingUpdater
     */
    private $variantListingUpdater;

    /**
     * @var ListingPriceUpdater
     */
    private $listingPriceUpdater;

    public function __construct(
        IteratorFactory $iteratorFactory,
        EntityRepositoryInterface $repository,
        Connection $connection,
        CacheClearer $cacheClearer,
        VariantListingUpdater $variantListingUpdater,
        ListingPriceUpdater $listingPriceUpdater
    ) {
        $this->iteratorFactory = $iteratorFactory;
        $this->repository = $repository;
        $this->connection = $connection;
        $this->cacheClearer = $cacheClearer;
        $this->variantListingUpdater = $variantListingUpdater;
        $this->listingPriceUpdater = $listingPriceUpdater;
    }

    public function getName(): string
    {
        return 'product.indexer';
    }

    public function iterate($offset): ?EntityIndexingMessage
    {
        $iterator = $this->iteratorFactory->createIterator($this->repository->getDefinition(), $offset);

        $ids = $iterator->fetch();

        if (empty($ids)) {
            return null;
        }

        return new EntityIndexingMessage($ids, $iterator->getOffset());
    }

    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        $updates = $event->getPrimaryKeys(ProductDefinition::ENTITY_NAME);

        if (empty($updates)) {
            return null;
        }

        return new EntityIndexingMessage($updates, null);
    }

    public function handle(EntityIndexingMessage $message): void
    {
        $ids = $message->getData();

        if (empty($ids)) {
            return;
        }

        $parentIds = $this->connection->fetchAll(
            'SELECT DISTINCT LOWER(HEX(IFNULL(product.parent_id, id))) as id FROM product WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );
        $parentIds = array_column($parentIds, 'id');

        $all = array_unique(array_filter(array_merge($ids, $parentIds)));

        $context = Context::createDefaultContext();

        $this->variantListingUpdater->update($parentIds, $context);

        $this->listingPriceUpdater->update($ids, $context);

        $this->cacheClearer->invalidateIds($all, ProductDefinition::ENTITY_NAME);

//        $this->eventDispatcher->dispatch()
    }
}
