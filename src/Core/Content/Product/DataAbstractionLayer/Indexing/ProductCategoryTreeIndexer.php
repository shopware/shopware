<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerInterface;
use Shopware\Core\Framework\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProductCategoryTreeIndexer implements IndexerInterface
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var IteratorFactory
     */
    private $iteratorFactory;

    /**
     * @var ProductDefinition
     */
    private $productDefinition;

    public function __construct(
        Connection $connection,
        EventDispatcherInterface $eventDispatcher,
        IteratorFactory $iteratorFactory,
        ProductDefinition $productDefinition
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->connection = $connection;
        $this->iteratorFactory = $iteratorFactory;
        $this->productDefinition = $productDefinition;
    }

    public function index(\DateTimeInterface $timestamp): void
    {
        $context = Context::createDefaultContext();

        $query = $this->iteratorFactory->createIterator($this->productDefinition);

        $this->eventDispatcher->dispatch(
            new ProgressStartedEvent('Start building product category assignment', $query->fetchCount()),
            ProgressStartedEvent::NAME
        );

        while ($ids = $query->fetch()) {
            $this->update($ids, $context);

            $this->eventDispatcher->dispatch(
                new ProgressAdvancedEvent(\count($ids)),
                ProgressAdvancedEvent::NAME
            );
        }

        $this->eventDispatcher->dispatch(
            new ProgressFinishedEvent('Finish building product category assignment'),
            ProgressFinishedEvent::NAME
        );
    }

    public function partial(?array $lastId, \DateTimeInterface $timestamp): ?array
    {
        $context = Context::createDefaultContext();

        $iterator = $this->iteratorFactory->createIterator($this->productDefinition, $lastId);

        $ids = $iterator->fetch();
        if (empty($ids)) {
            return null;
        }

        $this->update($ids, $context);

        return $iterator->getOffset();
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        $ids = [];

        $nested = $event->getEventByDefinition(ProductDefinition::class);
        if ($nested) {
            $ids = $nested->getIds();
        }

        $nested = $event->getEventByDefinition(ProductCategoryDefinition::class);
        if ($nested) {
            foreach ($nested->getIds() as $id) {
                $ids[] = $id['productId'];
            }
        }

        $this->update($ids, $event->getContext());
    }

    private function update(array $ids, Context $context): void
    {
        if (empty($ids)) {
            return;
        }

        $categories = $this->fetchCategories($ids, $context);

        $query = new MultiInsertQueryQueue($this->connection, 250, false, true);

        $versionId = Uuid::fromHexToBytes($context->getVersionId());
        $liveVersionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        foreach ($categories as $productId => $mapping) {
            $productId = Uuid::fromHexToBytes($productId);

            $categoryIds = $this->mapCategories($mapping);

            $json = null;
            if (!empty($categoryIds)) {
                $json = json_encode($categoryIds);
            }

            $this->connection->executeUpdate(
                'UPDATE product SET category_tree = :tree WHERE id = :id AND version_id = :version',
                [
                    'id' => $productId,
                    'tree' => $json,
                    'version' => $versionId,
                ]
            );

            if (empty($categoryIds)) {
                continue;
            }

            foreach ($categoryIds as $id) {
                $query->addInsert('product_category_tree', [
                    'product_id' => $productId,
                    'product_version_id' => $versionId,
                    'category_id' => Uuid::fromHexToBytes($id),
                    'category_version_id' => $liveVersionId,
                ]);
            }
        }

        $productIds = Uuid::fromHexToBytesList(array_keys($categories));

        $this->connection->executeUpdate(
            'DELETE FROM `product_category_tree` WHERE `product_id` IN (:ids)',
            ['ids' => $productIds],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        $query->execute();
    }

    private function fetchCategories(array $ids, Context $context): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select([
            'HEX(product.id) as product_id',
            "GROUP_CONCAT(category.path SEPARATOR '|') as paths",
            "GROUP_CONCAT(HEX(category.id) SEPARATOR '||') as ids",
        ]);
        $query->from('product');
        $query->leftJoin(
            'product',
            'product_category',
            'mapping',
            'mapping.product_id = product.categories AND mapping.product_version_id = product.version_id'
        );
        $query->leftJoin(
            'mapping',
            'category',
            'category',
            'mapping.category_id = category.id AND mapping.category_version_id = category.version_id AND mapping.category_version_id = :live'
        );

        $query->addGroupBy('product.id');

        $query->andWhere('product.id IN (:ids)');
        $query->andWhere('product.version_id = :version');

        $query->setParameter('version', Uuid::fromHexToBytes($context->getVersionId()));
        $query->setParameter('live', Uuid::fromHexToBytes(Defaults::LIVE_VERSION));

        $bytes = array_map(function (string $id) {
            return Uuid::fromHexToBytes($id);
        }, $ids);

        $query->setParameter('ids', $bytes, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll();

        return FetchModeHelper::groupUnique($rows);
    }

    private function mapCategories(array $mapping): array
    {
        $categoryIds = array_filter(explode('||', (string) $mapping['ids']));
        $categoryIds = array_merge(
            explode('|', (string) $mapping['paths']),
            $categoryIds
        );

        $categoryIds = array_map('strtolower', $categoryIds);

        return array_keys(array_flip(array_filter($categoryIds)));
    }
}
