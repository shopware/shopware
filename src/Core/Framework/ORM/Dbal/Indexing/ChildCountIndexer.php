<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Dbal\Indexing;

use Doctrine\DBAL\Connection;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\ProgressAdvancedEvent;
use Shopware\Framework\Event\ProgressFinishedEvent;
use Shopware\Framework\Event\ProgressStartedEvent;
use Shopware\Framework\ORM\Dbal\Common\IterableQuery;
use Shopware\Framework\ORM\Dbal\Common\LastIdQuery;
use Shopware\Framework\ORM\Dbal\Common\OffsetQuery;
use Shopware\Framework\ORM\DefinitionRegistry;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\Write\EntityExistence;
use Shopware\Framework\ORM\Write\GenericWrittenEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\Framework\Struct\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ChildCountIndexer implements IndexerInterface
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var DefinitionRegistry
     */
    private $definitionRegistry;

    public function __construct(
        Connection $connection,
        EventDispatcherInterface $eventDispatcher,
        DefinitionRegistry $definitionRegistry
    ) {
        $this->connection = $connection;
        $this->eventDispatcher = $eventDispatcher;
        $this->definitionRegistry = $definitionRegistry;
    }

    public function index(\DateTime $timestamp, string $tenantId): void
    {
        $context = ApplicationContext::createDefaultContext($tenantId);

        /** @var EntityDefinition $entityDefinition */
        foreach ($this->definitionRegistry->getElements() as $entityDefinition) {
            if (!$entityDefinition::isChildrenAware() || !$entityDefinition::isChildCountAware()) {
                continue;
            }

            $entityName = $entityDefinition::getEntityName();
            $iterator = $this->createIterator($tenantId, $entityName, $entityDefinition);

            $this->eventDispatcher->dispatch(
                ProgressStartedEvent::NAME,
                new ProgressStartedEvent('Start indexing child counts of ' . $entityName, $iterator->fetchCount())
            );

            while ($ids = $iterator->fetch()) {
                $ids = array_map(function ($id) {
                    return Uuid::fromBytesToHex($id);
                }, $ids);

                $this->updateChildCount($entityName, $ids, $context);

                $this->eventDispatcher->dispatch(
                    ProgressAdvancedEvent::NAME,
                    new ProgressAdvancedEvent(count($ids))
                );
            }

            $this->eventDispatcher->dispatch(
                ProgressFinishedEvent::NAME,
                new ProgressFinishedEvent('Finished indexing child count of ' . $entityName)
            );
        }
    }

    public function refresh(GenericWrittenEvent $event): void
    {
        /** @var WrittenEvent $nested */
        foreach ($event->getEvents() as $nested) {
            $definition = $nested->getDefinition();

            if ($definition::isChildrenAware() && $definition::isChildCountAware()) {
                $this->update($nested, $nested->getIds(), $nested->getContext());
            }
        }
    }

    private function update(WrittenEvent $event, array $ids, ApplicationContext $context)
    {
        $entityParents = array_map(function (EntityExistence $existence) {
            if (!array_key_exists('parent_id', $existence->getState()) || !$existence->getState()['parent_id']) {
                return null;
            }

            return Uuid::fromBytesToHex($existence->getState()['parent_id']);
        }, $event->getExistences());

        $entityName = $event->getDefinition()::getEntityName();

        $parentIds = $this->fetchParentIds($entityName, $ids, $context);
        $parentIds = array_keys(array_flip(array_filter(array_merge($entityParents, $parentIds))));

        $this->updateChildCount($entityName, $parentIds, $context);
    }

    private function updateChildCount(string $entityName, array $parentIds, ApplicationContext $context): void
    {
        if (empty($parentIds)) {
            return;
        }

        $versionId = Uuid::fromStringToBytes($context->getVersionId());
        $tenantId = Uuid::fromHexToBytes($context->getTenantId());

        $parentIds = array_map(function ($id) {
            return Uuid::fromHexToBytes($id);
        }, $parentIds);

        $this->validateTableName($entityName);

        $this->connection->executeQuery('
            UPDATE `' . $entityName . '`  as parent
                    LEFT JOIN
                    (
                        SELECT parent_id, count(id) total
                        FROM    `' . $entityName . '`
                        WHERE version_id = :version AND tenant_id = :tenant
                        GROUP BY parent_id
                    ) child ON parent.id = child.parent_id
            SET parent.child_count = IFNULL(child.total, 0)
            WHERE parent.id IN (:ids)
            AND parent.version_id = :version
            AND parent.tenant_id = :tenant',
            [
                'ids' => $parentIds,
                'version' => $versionId,
                'tenant' => $tenantId,
            ],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );
    }

    private function fetchParentIds(string $entityName, array $ids, ApplicationContext $context): array
    {
        $ids = array_map(function ($id) {
            return Uuid::fromStringToBytes($id);
        }, $ids);

        $this->validateTableName($entityName);

        $query = $this->connection->createQueryBuilder();
        $query->select(['parent_id']);
        $query->from($entityName);
        $query->andWhere('id IN (:ids)');
        $query->andWhere('tenant_id = :tenant');
        $query->andWhere('version_id = :version');

        $query->setParameter('version', Uuid::fromStringToBytes($context->getVersionId()));
        $query->setParameter('tenant', Uuid::fromStringToBytes($context->getTenantId()));
        $query->setParameter('ids', $ids, Connection::PARAM_STR_ARRAY);

        $parents = $query->execute()->fetchAll(\PDO::FETCH_COLUMN);
        $parents = array_filter($parents);

        return array_map(function (string $id) {
            return Uuid::fromBytesToHex($id);
        }, $parents);
    }

    private function createIterator(string $tenantId, string $entityName, $entityDefinition): IterableQuery
    {
        $query = $this->connection->createQueryBuilder();

        $query->from($entityName);
        $query->andWhere('tenant_id = :tenantId');

        $query->setMaxResults(50);

        $query->setParameter('tenantId', Uuid::fromHexToBytes($tenantId));

        if ($entityDefinition::getFields()->has('autoIncrement')) {
            $query->select(['auto_increment', 'id']);
            $query->andWhere('auto_increment > :lastId');
            $query->addOrderBy('auto_increment');
            $query->setParameter('lastId', 0);

            return new LastIdQuery($query);
        }
        $query->select(['id']);

        return new OffsetQuery($query);
    }

    private function validateTableName(string $tableName)
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $tableName)) {
            throw new \Exception(sprintf('Invalid table name %s', $tableName));
        }
    }
}
