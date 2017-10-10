<?php declare(strict_types=1);

namespace Shopware\DbalIndexing\Indexer;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\DbalIndexing\Common\RepositoryIterator;
use Shopware\DbalIndexing\Event\ProgressAdvancedEvent;
use Shopware\DbalIndexing\Event\ProgressFinishedEvent;
use Shopware\DbalIndexing\Event\ProgressStartedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Product\Event\ProductCategoryWrittenEvent;
use Shopware\Product\Repository\ProductRepository;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProductCategoryIndexer implements IndexerInterface
{
    const TABLE = 'product_category_ro';

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

    public function __construct(
        ProductRepository $productRepository,
        Connection $connection,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->productRepository = $productRepository;
        $this->connection = $connection;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function index(TranslationContext $context, \DateTime $timestamp): void
    {
        if ($context->getShopUuid() !== 'SWAG-SHOP-UUID-1') {
            return;
        }
        $iterator = new RepositoryIterator($this->productRepository, $context);

        $this->createTable($timestamp);

        $this->eventDispatcher->dispatch(
            ProgressStartedEvent::NAME,
            new ProgressStartedEvent('Start building category product tree', $iterator->getTotal())
        );

        while ($uuids = $iterator->fetchUuids()) {
            $this->indexCategoryAssignment($uuids, $context, $timestamp);

            $this->eventDispatcher->dispatch(
                ProgressAdvancedEvent::NAME,
                new ProgressAdvancedEvent(count($uuids))
            );
        }

        $this->renameTable($timestamp);

        $this->eventDispatcher->dispatch(
            ProgressFinishedEvent::NAME,
            new ProgressFinishedEvent('Finished building category product tree')
        );
    }

    public function refresh(NestedEventCollection $events, TranslationContext $context): void
    {
        $uuids = $this->getProductUuids($events);
        if (empty($uuids)) {
            return;
        }

        $this->connection->executeUpdate(
            'DELETE FROM product_category_ro WHERE product_uuid IN (:uuids)',
            ['uuids' => $uuids],
            ['uuids' => Connection::PARAM_STR_ARRAY]
        );
        $this->indexCategoryAssignment($uuids, $context, null);
    }

    private function indexCategoryAssignment(array $uuids, TranslationContext $context, ?\DateTime $timestamp): void
    {
        $categories = $this->fetchCategories($uuids);

        $table = $this->getIndexName($timestamp);

        $insert = $this->connection->prepare(
            'INSERT IGNORE INTO ' . $table . ' (product_uuid, category_uuid) VALUES (:product_uuid, :category_uuid)'
        );

        foreach ($categories as $productUuid => $mapping) {
            $categoryUuids = array_merge(
                explode('|', (string) $mapping['paths']),
                explode('|', (string) $mapping['uuids'])
            );

            $categoryUuids = array_keys(array_flip(array_filter($categoryUuids)));

            foreach ($categoryUuids as $uuid) {
                $insert->execute([
                    'product_uuid' => $productUuid,
                    'category_uuid' => $uuid,
                ]);
            }
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

    private function getIndexName(?\DateTime $timestamp): string
    {
        if ($timestamp === null) {
            return self::TABLE;
        }

        return self::TABLE . '_' . $timestamp->format('YmdHis');
    }

    /**
     * @param NestedEventCollection $events
     *
     * @return array
     */
    private function getProductUuids(NestedEventCollection $events): array
    {
        /** @var NestedEventCollection $events */
        $events = $events
            ->getFlatEventList()
            ->filterInstance(ProductCategoryWrittenEvent::class);

        $uuids = [];
        /** @var ProductCategoryWrittenEvent $event */
        foreach ($events as $event) {
            foreach ($event->getProductUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    private function renameTable(\DateTime $timestamp): void
    {
        $this->connection->transactional(function () use ($timestamp) {
            $name = $this->getIndexName($timestamp);
            $this->connection->executeUpdate('DROP TABLE ' . self::TABLE);
            $this->connection->executeUpdate('ALTER TABLE ' . $name . ' RENAME TO ' . self::TABLE);
        });
    }

    private function createTable(\DateTime $timestamp): void
    {
        $name = $this->getIndexName($timestamp);
        $this->connection->executeUpdate('
            DROP TABLE IF EXISTS ' . $name . ';
            CREATE TABLE ' . $name . ' SELECT * FROM ' . self::TABLE . ' LIMIT 0
        ');
    }
}
