<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerInterface;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class VariantListingIndexer implements IndexerInterface
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
        EventDispatcherInterface $eventDispatcher,
        Connection $connection,
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

        $iterator = $this->iteratorFactory->createIterator($this->productDefinition);
        $iterator->getQuery()->andWhere('product.parent_id IS NULL');

        $this->eventDispatcher->dispatch(
            new ProgressStartedEvent('Start indexing listing variants', $iterator->fetchCount()),
            ProgressStartedEvent::NAME
        );

        while ($ids = $iterator->fetch()) {
            $this->update($ids, $context);

            $this->eventDispatcher->dispatch(
                new ProgressAdvancedEvent(\count($ids)),
                ProgressAdvancedEvent::NAME
            );
        }

        $this->eventDispatcher->dispatch(
            new ProgressFinishedEvent('Finished indexing listing variants'),
            ProgressFinishedEvent::NAME
        );
    }

    public function partial(?array $lastId, \DateTimeInterface $timestamp): ?array
    {
        $context = Context::createDefaultContext();

        $iterator = $this->iteratorFactory->createIterator($this->productDefinition, $lastId);
        $iterator->getQuery()->andWhere('product.parent_id IS NULL');

        $ids = $iterator->fetch();
        if (empty($ids)) {
            return null;
        }

        $this->update($ids, $context);

        return $iterator->getOffset();
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        $products = $event->getEventByEntityName(ProductDefinition::ENTITY_NAME);

        $ids = [];
        if ($products) {
            $ids = $products->getIds();
        }

        $query = $this->connection->createQueryBuilder();
        $query->addSelect('LOWER(HEX(IFNULL(product.parent_id, product.id))) as product_id');
        $query->from('product');
        $query->where('product.id IN (:ids)');
        $query->setParameter('ids', Uuid::fromHexToBytesList($ids), Connection::PARAM_STR_ARRAY);

        $ids = $query->execute()->fetchAll(\PDO::FETCH_COLUMN);
        $ids = array_filter($ids);

        $this->update($ids, $event->getContext());
    }

    public static function getName(): string
    {
        return 'Swag.VariantListingIndexer';
    }

    private function getListingConfiguration(array $ids, Context $context)
    {
        $versionBytes = Uuid::fromHexToBytes($context->getVersionId());

        $query = $this->connection->createQueryBuilder();
        $query->select(['product.id as id', 'product.configurator_group_config as config', '(SELECT COUNT(id) FROM product as child WHERE product.id = child.parent_id) as child_count']);
        $query->from('product');
        $query->andWhere('product.version_id = :version');
        $query->andWhere('product.id IN (:ids)');
        $query->setParameter('ids', Uuid::fromHexToBytesList($ids), Connection::PARAM_STR_ARRAY);
        $query->setParameter('version', $versionBytes);

        $configuration = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);

        $listingConfiguration = [];
        foreach ($configuration as $config) {
            $config['config'] = $config['config'] === null ? [] : json_decode($config['config'], true);

            $groups = [];
            foreach ($config['config'] as $group) {
                if ($group['expressionForListings']) {
                    $groups[] = $group['id'];
                }
            }

            $listingConfiguration[$config['id']] = [
                'groups' => $groups,
                'child_count' => $config['child_count'],
            ];
        }

        return $listingConfiguration;
    }

    private function update(array $ids, Context $context): void
    {
        if (empty($ids)) {
            return;
        }

        $ids = array_keys(array_flip($ids));

        $versionBytes = Uuid::fromHexToBytes($context->getVersionId());

        $listingConfiguration = $this->getListingConfiguration($ids, $context);

        foreach ($listingConfiguration as $parentId => $config) {
            $childCount = (int) $config['child_count'];
            $groups = $config['groups'];

            if ($childCount <= 0) {
                $this->connection->executeUpdate(
                    'UPDATE product SET display_group = MD5(HEX(product.id)) WHERE product.id = :id AND product.version_id = :versionId',
                    ['id' => $parentId, 'versionId' => $versionBytes]
                );
            } else {
                $this->connection->executeUpdate(
                    'UPDATE product SET display_group = NULL WHERE product.id = :id AND product.version_id = :versionId',
                    ['id' => $parentId, 'versionId' => $versionBytes]
                );
            }

            if (empty($groups)) {
                $this->connection->executeUpdate(
                    'UPDATE product SET display_group = MD5(HEX(product.parent_id)) WHERE product.parent_id = :id AND product.version_id = :versionId',
                    ['id' => $parentId, 'versionId' => $versionBytes]
                );

                continue;
            }

            $query = $this->connection->createQueryBuilder();

            $query->from('(SELECT 1)', 'root');

            $fields = [];
            $params = ['parentId' => $parentId];
            foreach ($groups as $groupId) {
                $mappingAlias = 'mapping' . $groupId;
                $optionAlias = 'option' . $groupId;

                $query->innerJoin('root', 'product_option', $mappingAlias, $mappingAlias . '.product_id IS NOT NULL');
                $query->innerJoin($mappingAlias, 'property_group_option', $optionAlias, $optionAlias . '.id = ' . $mappingAlias . '.property_group_option_id AND ' . $optionAlias . '.property_group_id = :' . $optionAlias);
                $query->andWhere($mappingAlias . '.product_id = product.id');

                $fields[] = 'LOWER(HEX(' . $optionAlias . '.id))';

                $params[$optionAlias] = Uuid::fromHexToBytes($groupId);
            }

            $query->addSelect('CONCAT(' . implode(',', $fields) . ')');

            $sql = '
            UPDATE product SET display_group = MD5(
                CONCAT(
                    LOWER(HEX(product.parent_id)),
                    (' . $query->getSQL() . ')
                )
            ) WHERE parent_id = :parentId';

            $this->connection->executeUpdate($sql, $params);
        }
    }
}
