<?php declare(strict_types=1);

namespace Shopware\DbalIndexing\ProductCategory;

use Doctrine\DBAL\Connection;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\Query\TermQuery;
use Shopware\Api\Entity\Write\GenericWrittenEvent;
use Shopware\Api\Product\Definition\ProductCategoryDefinition;
use Shopware\Api\Product\Repository\ProductRepository;
use Shopware\Api\Shop\Repository\ShopRepository;
use Shopware\Api\Shop\Struct\ShopBasicStruct;
use Shopware\Category\Extension\CategoryPathBuilder;
use Shopware\Context\Struct\TranslationContext;
use Shopware\DbalIndexing\Common\RepositoryIterator;
use Shopware\DbalIndexing\Event\ProgressAdvancedEvent;
use Shopware\DbalIndexing\Event\ProgressFinishedEvent;
use Shopware\DbalIndexing\Event\ProgressStartedEvent;
use Shopware\DbalIndexing\Indexer\IndexerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProductCategoryIndexer implements IndexerInterface
{
    /**
     * @var ProductRepository
     */
    private $productRepository;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var CategoryPathBuilder
     */
    private $pathBuilder;

    /**
     * @var ShopRepository
     */
    private $shopRepository;

    public function __construct(
        ProductRepository $productRepository,
        Connection $connection,
        EventDispatcherInterface $eventDispatcher,
        CategoryPathBuilder $pathBuilder,
        ShopRepository $shopRepository
    ) {
        $this->productRepository = $productRepository;
        $this->connection = $connection;
        $this->eventDispatcher = $eventDispatcher;
        $this->pathBuilder = $pathBuilder;
        $this->shopRepository = $shopRepository;
    }

    public function index(\DateTime $timestamp): void
    {
        $shop = $this->getDefaultShop();

        $context = TranslationContext::createFromShop($shop);

        $this->pathBuilder->update('SWAG-CATEGORY-UUID-1', $context);

        $iterator = new RepositoryIterator($this->productRepository, $context);

        $this->eventDispatcher->dispatch(
            ProgressStartedEvent::NAME,
            new ProgressStartedEvent('Start building category product tree', $iterator->getTotal())
        );

        while ($uuids = $iterator->fetchUuids()) {
            $this->indexCategoryAssignment($uuids);

            $this->eventDispatcher->dispatch(
                ProgressAdvancedEvent::NAME,
                new ProgressAdvancedEvent(count($uuids))
            );
        }

        $this->eventDispatcher->dispatch(
            ProgressFinishedEvent::NAME,
            new ProgressFinishedEvent('Finished building category product tree')
        );
    }

    public function refresh(GenericWrittenEvent $event): void
    {
        $productUuids = $this->getProductUuids($event);
        if (empty($productUuids)) {
            return;
        }

        $this->connection->transactional(function () use ($productUuids) {
            $this->indexCategoryAssignment($productUuids);
        });
    }

    private function indexCategoryAssignment(array $uuids): void
    {
        $categories = $this->fetchCategories($uuids);

        foreach ($categories as $productUuid => $mapping) {
            $categoryUuids = array_merge(
                explode('|', (string) $mapping['paths']),
                explode('|', (string) $mapping['uuids'])
            );

            $categoryUuids = array_keys(array_flip(array_filter($categoryUuids)));

            $this->connection->executeUpdate(
                'UPDATE product SET category_tree = :tree WHERE uuid = :uuid',
                ['uuid' => $productUuid, 'tree' => json_encode($categoryUuids)]
            );
        }
    }

    private function fetchCategories(array $uuids): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select([
            'product.uuid as product_uuid',
            "GROUP_CONCAT(category.path SEPARATOR '|') as paths",
            "GROUP_CONCAT(category.uuid SEPARATOR '|') as uuids",
        ]);
        $query->from('product');
        $query->leftJoin('product', 'product_category', 'mapping', 'mapping.product_uuid = product.uuid');
        $query->leftJoin('mapping', 'category', 'category', 'category.uuid = mapping.category_uuid');
        $query->addGroupBy('product.uuid');
        $query->andWhere('product.uuid IN (:uuids)');
        $query->setParameter(':uuids', $uuids, Connection::PARAM_STR_ARRAY);

        return $query->execute()->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_UNIQUE);
    }

    private function getProductUuids(GenericWrittenEvent $event): array
    {
        $productEvent = $event->getEventByDefinition(ProductCategoryDefinition::class);

        if (!$productEvent) {
            return [];
        }

        $uuids = [];

        foreach ($productEvent->getUuids() as $uuid) {
            $uuids[] = $uuid['productUuid'];
        }

        return $uuids;
    }

    private function getDefaultShop(): ShopBasicStruct
    {
        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('shop.isDefault', true));
        $result = $this->shopRepository->search($criteria, TranslationContext::createDefaultContext());

        return $result->first();
    }
}
