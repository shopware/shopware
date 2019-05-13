<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Indexing\IndexerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
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

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        $products = $event->getEventByDefinition(ProductDefinition::class);

        $ids = [];
        if ($products) {
            $ids = $products->getIds();
        }

        $this->update($ids, $event->getContext());
    }

    private function getListingConfiguration(array $ids, Context $context)
    {
        $versionBytes = Uuid::fromHexToBytes($context->getVersionId());

        $query = $this->connection->createQueryBuilder();
        $query->select(['IFNULL(parent.id, product.id) as id', 'IFNULL(parent.configurator_group_config, product.configurator_group_config) as config']);
        $query->from('product');
        $query->leftJoin('product', 'product', 'parent', 'parent.id = product.parent_id');
        $query->andWhere('product.version_id = :version');
        $query->andWhere('product.id IN (:ids)');
        $query->setParameter('ids', Uuid::fromHexToBytesList($ids), Connection::PARAM_STR_ARRAY);
        $query->setParameter('version', $versionBytes);

        $configuration = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);

        $listingConfiguration = [];
        foreach ($configuration as $config) {
            $config['config'] = $config['config'] === null ? [] : json_decode($config['config'], true);

            $listing = [];
            foreach ($config['config'] as $group) {
                if ($group['expressionForListings']) {
                    $listing[] = $group['id'];
                }
            }

            $listingConfiguration[$config['id']] = $listing;
        }

        return $listingConfiguration;
    }

    private function update(array $ids, Context $context): void
    {
        if (empty($ids)) {
            return;
        }
        $versionBytes = Uuid::fromHexToBytes($context->getVersionId());

        $listingConfiguration = $this->getListingConfiguration($ids, $context);

        foreach ($listingConfiguration as $parentId => $config) {
            // display only "container" product, if the config is empty
            if (empty($config)) {
                $this->connection->executeUpdate(
                    'UPDATE product SET display_in_listing = 0 WHERE product.parent_id = :id AND product.version_id = :versionId',
                    ['id' => $parentId, 'versionId' => $versionBytes]
                );
                $this->connection->executeUpdate(
                    'UPDATE product SET display_in_listing = 1 WHERE product.id = :id AND product.version_id = :versionId',
                    ['id' => $parentId, 'versionId' => $versionBytes]
                );
                continue;
            }
            $query = $this->connection->createQueryBuilder();

            $query->select('product.id');
            $query->from('product');
            $query->andWhere('product.version_id = :version');
            $query->andWhere('product.parent_id = :parentId');
            $query->andWhere('product.active = 1');
            $query->setParameter('parentId', $parentId);
            $query->setParameter('version', $versionBytes);

            foreach ($config as $groupId) {
                $groupAlias = 'group_' . $groupId;
                $mappingAlias = 'mapping' . $groupId;
                $optionAlias = 'option' . $groupId;

                //INNER JOIN product_option color_mapping ON color_mapping.product_id = product.id
                $query->innerJoin('product', 'product_option', $mappingAlias, $mappingAlias . '.product_id = product.id');

                //INNER JOIN property_group_option colors ON colors.id = color_mapping.property_group_option_id
                $query->innerJoin($mappingAlias, 'property_group_option', $optionAlias, $mappingAlias . '.property_group_option_id = ' . $optionAlias . '.id');

                //INNER JOIN property_group color ON color.id = colors.property_group_id AND color.id = UNHEX('e5ebdf386d6043be92014b38bcfec0d5')
                $query->innerJoin($optionAlias, 'property_group', $groupAlias, $optionAlias . '.property_group_id = ' . $groupAlias . '.id AND ' . $groupAlias . '.id = :' . $groupAlias);

                $query->addGroupBy($optionAlias . '.id');

                $query->setParameter($groupAlias, Uuid::fromHexToBytes($groupId));
            }

            $ids = $query->execute()->fetchAll(\PDO::FETCH_COLUMN);

            // disable all variants and the "container" product
            $this->connection->executeUpdate(
                'UPDATE product SET display_in_listing = 0 WHERE (product.parent_id = :id OR (product.id = :id))  AND product.version_id = :versionId',
                ['id' => $parentId, 'versionId' => $versionBytes]
            );

            // no variants found? display "container" product
            if (!empty($ids)) {
                // activate found variants for listings
                $this->connection->executeUpdate(
                    'UPDATE product SET display_in_listing = 1 WHERE product.id IN (:ids) AND product.version_id = :versionId',
                    ['ids' => $ids, 'versionId' => $versionBytes],
                    ['ids' => Connection::PARAM_STR_ARRAY]
                );

                continue;
            }

            $available = $this->connection->fetchColumn(
                'SELECT 1 FROM `product` WHERE `parent_id` = :parentId AND `active` = 1 LIMIT 1',
                ['parentId' => $parentId]
            );

            // product has no more available variant
            if (!$available) {
                continue;
            }

            $this->connection->executeUpdate(
                'UPDATE product SET display_in_listing = 1 WHERE product.parent_id = :id AND product.version_id = :versionId',
                ['id' => $parentId, 'versionId' => $versionBytes]
            );
        }
    }
}
