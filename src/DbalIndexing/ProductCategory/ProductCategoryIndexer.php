<?php declare(strict_types=1);

namespace Shopware\DbalIndexing\ProductCategory;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;
use Shopware\Api\Entity\Dbal\EntityDefinitionResolver;
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
use Shopware\Defaults;
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

        $this->pathBuilder->update(Defaults::ROOT_CATEGORY, $context);

        $iterator = new RepositoryIterator($this->productRepository, $context);

        $this->eventDispatcher->dispatch(
            ProgressStartedEvent::NAME,
            new ProgressStartedEvent('Start building category product tree', $iterator->getTotal())
        );

        while ($ids = $iterator->fetchIds()) {
            $this->indexCategoryAssignment($ids);

            $this->eventDispatcher->dispatch(
                ProgressAdvancedEvent::NAME,
                new ProgressAdvancedEvent(count($ids))
            );
        }

        $this->eventDispatcher->dispatch(
            ProgressFinishedEvent::NAME,
            new ProgressFinishedEvent('Finished building category product tree')
        );
    }

    public function refresh(GenericWrittenEvent $event): void
    {
        $productIds = $this->getRefreshedProductIds($event);
        if (empty($productIds)) {
            return;
        }

        $this->connection->transactional(function () use ($productIds) {
            $this->indexCategoryAssignment($productIds);
        });
    }

    private function indexCategoryAssignment(array $ids): void
    {
        $categories = $this->fetchCategories($ids);

        foreach ($categories as $productId => $mapping) {
            $categoryIds = array_filter(explode('||', (string) $mapping['ids']));
            $categoryIds = array_map(
                function (string $bytes) {
                    return Uuid::fromString($bytes)->toString();
                },
                $categoryIds
            );

            $categoryIds = array_merge(
                explode('|', (string) $mapping['paths']),
                $categoryIds
            );

            $categoryIds = array_keys(array_flip(array_filter($categoryIds)));

            $this->connection->executeUpdate(
                'UPDATE product SET category_tree = :tree WHERE id = :id',
                ['id' => $productId, 'tree' => json_encode($categoryIds)]
            );
        }
    }

    private function fetchCategories(array $ids): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select([
            'product.id as product_id',
            "GROUP_CONCAT(category.path SEPARATOR '|') as paths",
            "GROUP_CONCAT(HEX(category.id) SEPARATOR '||') as ids",
        ]);
        $query->from('product');
        $query->leftJoin('product', 'product_category', 'mapping', 'mapping.product_id = product.id');
        $query->leftJoin('mapping', 'category', 'category', 'category.id = mapping.category_id');
        $query->addGroupBy('product.id');
        $query->andWhere('product.id IN (:ids)');

        $bytes = EntityDefinitionResolver::uuidStringsToBytes($ids);

        $query->setParameter('ids', $bytes, Connection::PARAM_STR_ARRAY);

        return $query->execute()->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_UNIQUE);
    }

    private function getRefreshedProductIds(GenericWrittenEvent $event): array
    {
        $productEvent = $event->getEventByDefinition(ProductCategoryDefinition::class);

        if (!$productEvent) {
            return [];
        }

        $ids = [];

        foreach ($productEvent->getIds() as $id) {
            $ids[] = $id['productId'];
        }

        return $ids;
    }

    private function getDefaultShop(): ShopBasicStruct
    {
        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('shop.isDefault', true));
        $result = $this->shopRepository->search($criteria, TranslationContext::createDefaultContext());

        return $result->first();
    }
}
