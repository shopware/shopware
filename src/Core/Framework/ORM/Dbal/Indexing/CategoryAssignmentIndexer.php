<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Dbal\Indexing;

use Doctrine\DBAL\Connection;
use Shopware\Content\Category\CategoryRepository;
use Shopware\Framework\ORM\Dbal\Common\LastIdQuery;
use Shopware\Framework\ORM\Search\Criteria;
use Shopware\Framework\ORM\Search\Query\TermQuery;
use Shopware\Framework\ORM\Write\GenericWrittenEvent;
use Shopware\Content\Product\ProductRepository;
use Shopware\Content\Category\Util\CategoryPathBuilder;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\ORM\Dbal\Common\EventIdExtractor;
use Shopware\Framework\Event\ProgressAdvancedEvent;
use Shopware\Framework\Event\ProgressFinishedEvent;
use Shopware\Framework\Event\ProgressStartedEvent;
use Shopware\Defaults;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Shopware\Framework\Doctrine\MultiInsertQueryQueue;
use Shopware\Framework\Struct\Uuid;

class CategoryAssignmentIndexer implements IndexerInterface
{
    /**
     * @var ProductRepository
     */
    private $productRepository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var \Shopware\Content\Category\Util\CategoryPathBuilder
     */
    private $pathBuilder;

    /**
     * @var \Shopware\Framework\ORM\Dbal\Common\EventIdExtractor
     */
    private $eventIdExtractor;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var \Shopware\Content\Category\CategoryRepository
     */
    private $categoryRepository;

    public function __construct(
        ProductRepository $productRepository,
        Connection $connection,
        EventDispatcherInterface $eventDispatcher,
        CategoryPathBuilder $pathBuilder,
        EventIdExtractor $eventIdExtractor,
        CategoryRepository $categoryRepository
    ) {
        $this->productRepository = $productRepository;
        $this->eventDispatcher = $eventDispatcher;
        $this->pathBuilder = $pathBuilder;
        $this->eventIdExtractor = $eventIdExtractor;
        $this->connection = $connection;
        $this->categoryRepository = $categoryRepository;
    }

    public function index(\DateTime $timestamp, string $tenantId): void
    {
        $context = ApplicationContext::createDefaultContext($tenantId);

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
            $ids = array_map(function($id) {
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

    public function refresh(GenericWrittenEvent $event): void
    {
        $ids = $this->eventIdExtractor->getProductIds($event);
        $this->update($ids, $event->getContext());
    }

    private function update(array $ids, ApplicationContext $context): void
    {
        if (empty($ids)) {
            return;
        }

        $categories = $this->fetchCategories($ids, $context);

        $query = new MultiInsertQueryQueue($this->connection);

        $versionId = Uuid::fromStringToBytes($context->getVersionId());
        $liveVersionId = Uuid::fromStringToBytes(Defaults::LIVE_VERSION);

        $tenantId = Uuid::fromStringToBytes($context->getTenantId());
        foreach ($categories as $productId => $mapping) {
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

    private function fetchCategories(array $ids, ApplicationContext $context): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select([
            'product.id as product_id',
            "GROUP_CONCAT(category.path SEPARATOR '|') as paths",
            "GROUP_CONCAT(HEX(category.id) SEPARATOR '||') as ids",
        ]);
        $query->from('product');
        $query->leftJoin('product', 'product_category', 'mapping', 'mapping.product_id = product.categories AND product.version_id = mapping.product_version_id AND product.tenant_id = mapping.product_tenant_id');
        $query->leftJoin('mapping', 'category', 'category', 'category.id = mapping.category_id AND category.version_id = :live AND category.tenant_id = product.tenant_id');
        $query->addGroupBy('product.id');

        $query->andWhere('product.id IN (:ids)');
        $query->andWhere('product.version_id = :version');
        $query->andWhere('product.tenant_id = :tenant');

        $query->setParameter('tenant', Uuid::fromStringToBytes($context->getTenantId()));
        $query->setParameter('version', Uuid::fromStringToBytes($context->getVersionId()));
        $query->setParameter('live', Uuid::fromStringToBytes(Defaults::LIVE_VERSION));

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
        $query->select(['category.ai', 'category.id']);
        $query->from('category');
        $query->andWhere('category.tenant_id = :tenantId');
        $query->andWhere('category.ai > :lastId');
        $query->addOrderBy('category.ai');

        $query->setMaxResults(50);

        $query->setParameter('tenantId', Uuid::fromHexToBytes($tenantId));
        $query->setParameter('lastId', 0);

        return new LastIdQuery($query);
    }
}
