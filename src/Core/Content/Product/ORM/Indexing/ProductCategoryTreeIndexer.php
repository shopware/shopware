<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\ORM\Indexing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Category\Util\CategoryPathBuilder;
use Shopware\Core\Content\Product\Util\EventIdExtractor;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Shopware\Core\Framework\ORM\Dbal\Common\LastIdQuery;
use Shopware\Core\Framework\ORM\Dbal\Indexing\IndexerInterface;
use Shopware\Core\Framework\ORM\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\Query\TermQuery;
use Shopware\Core\Framework\Struct\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProductCategoryTreeIndexer implements IndexerInterface
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var CategoryPathBuilder
     */
    private $pathBuilder;

    /**
     * @var EventIdExtractor
     */
    private $eventIdExtractor;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var RepositoryInterface
     */
    private $categoryRepository;

    public function __construct(
        Connection $connection,
        EventDispatcherInterface $eventDispatcher,
        CategoryPathBuilder $pathBuilder,
        EventIdExtractor $eventIdExtractor,
        RepositoryInterface $categoryRepository
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->pathBuilder = $pathBuilder;
        $this->eventIdExtractor = $eventIdExtractor;
        $this->connection = $connection;
        $this->categoryRepository = $categoryRepository;
    }

    public function index(\DateTime $timestamp, string $tenantId): void
    {
        $context = Context::createDefaultContext($tenantId);

        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('category.parentId', null));

        $categoryResult = $this->categoryRepository->searchIds($criteria, $context);

        $this->eventDispatcher->dispatch(
            ProgressStartedEvent::NAME,
            new ProgressStartedEvent('Start building category paths', $categoryResult->getTotal())
        );

        foreach ($categoryResult->getIds() as $categoryId) {
            $this->pathBuilder->update($categoryId, $context);

            $this->eventDispatcher->dispatch(ProgressAdvancedEvent::NAME, new ProgressAdvancedEvent());
        }

        $this->eventDispatcher->dispatch(
            ProgressFinishedEvent::NAME,
            new ProgressFinishedEvent('Finished building category paths')
        );

        $query = $this->createIterator($tenantId);

        $this->eventDispatcher->dispatch(
            ProgressStartedEvent::NAME,
            new ProgressStartedEvent('Start building product category assignment', $query->fetchCount())
        );

        while ($ids = $query->fetch()) {
            $ids = array_map(function ($id) {
                return Uuid::fromBytesToHex($id);
            }, $ids);

            $this->update($ids, $context);

            $this->eventDispatcher->dispatch(
                ProgressAdvancedEvent::NAME,
                new ProgressAdvancedEvent(count($ids))
            );
        }

        $this->eventDispatcher->dispatch(
            ProgressFinishedEvent::NAME,
            new ProgressFinishedEvent('Finish building product category assignment')
        );
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        $ids = $this->eventIdExtractor->getProductIds($event);
        $this->update($ids, $event->getContext());
    }

    private function update(array $ids, Context $context): void
    {
        if (empty($ids)) {
            return;
        }

        $categories = $this->fetchCategories($ids, $context);

        $query = new MultiInsertQueryQueue($this->connection, 250, false, true);

        $versionId = Uuid::fromStringToBytes($context->getVersionId());
        $liveVersionId = Uuid::fromStringToBytes(Defaults::LIVE_VERSION);

        $tenantId = Uuid::fromStringToBytes($context->getTenantId());

        foreach ($categories as $productId => $mapping) {
            $productId = Uuid::fromHexToBytes($productId);

            $categoryIds = $this->mapCategories($mapping);

            $json = null;
            if (!empty($categoryIds)) {
                $json = json_encode($categoryIds);
            }

            $this->connection->executeUpdate(
                'UPDATE product SET category_tree = :tree WHERE id = :id AND version_id = :version AND tenant_id = :tenant',
                [
                    'id' => $productId,
                    'tree' => $json,
                    'version' => $versionId,
                    'tenant' => $tenantId,
                ]
            );

            if ($categoryIds === null) {
                continue;
            }

            foreach ($categoryIds as $id) {
                $query->addInsert('product_category_tree', [
                    'product_id' => $productId,
                    'product_tenant_id' => $tenantId,
                    'product_version_id' => $versionId,
                    'category_id' => Uuid::fromStringToBytes($id),
                    'category_tenant_id' => $tenantId,
                    'category_version_id' => $liveVersionId,
                ]);
            }
        }

        $this->connection->executeUpdate(
            'DELETE FROM product_category_tree WHERE product_id IN (:ids) AND product_tenant_id = :tenant',
            ['ids' => array_keys($categories), 'tenant' => $tenantId],
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
            'mapping.product_id = product.categories AND 
             mapping.product_version_id = product.version_id AND 
             mapping.product_tenant_id = product.tenant_id'
        );
        $query->leftJoin(
            'mapping',
            'category',
            'category',
            'mapping.category_id = category.id AND 
             mapping.category_version_id = category.version_id AND
             mapping.category_tenant_id = category.tenant_id AND
             mapping.category_version_id = :live'
        );

        $query->addGroupBy('product.id');

        $query->andWhere('product.id IN (:ids)');
        $query->andWhere('product.version_id = :version');
        $query->andWhere('product.tenant_id = :tenant');

        $query->setParameter('tenant', Uuid::fromHexToBytes($context->getTenantId()));
        $query->setParameter('version', Uuid::fromHexToBytes($context->getVersionId()));
        $query->setParameter('live', Uuid::fromHexToBytes(Defaults::LIVE_VERSION));

        $bytes = array_map(function (string $id) {
            return Uuid::fromStringToBytes($id);
        }, $ids);

        $query->setParameter('ids', $bytes, Connection::PARAM_STR_ARRAY);

        return $query->execute()->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_UNIQUE);
    }

    private function mapCategories(array $mapping): array
    {
        $categoryIds = array_filter(explode('||', (string) $mapping['ids']));
        $categoryIds = array_map(
            function (string $bytes) {
                return Uuid::fromStringToHex($bytes);
            },
            $categoryIds
        );

        $categoryIds = array_merge(
            explode('|', (string) $mapping['paths']),
            $categoryIds
        );

        $categoryIds = array_map('strtolower', $categoryIds);

        return array_keys(array_flip(array_filter($categoryIds)));
    }

    private function createIterator(string $tenantId): LastIdQuery
    {
        $query = $this->connection->createQueryBuilder();
        $query->select(['product.auto_increment', 'product.id']);
        $query->from('product');
        $query->andWhere('product.tenant_id = :tenantId');
        $query->andWhere('product.auto_increment > :lastId');
        $query->addOrderBy('product.auto_increment');

        $query->setMaxResults(50);

        $query->setParameter('tenantId', Uuid::fromHexToBytes($tenantId));
        $query->setParameter('lastId', 0);

        return new LastIdQuery($query);
    }
}
