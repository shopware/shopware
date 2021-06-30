<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommit\VersionCommitDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommit\VersionCommitEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommitData\VersionCommitDataDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommitData\VersionCommitDataEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Version\VersionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\CloneBehavior;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DeleteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @internal
 */
class VersionManager
{
    public const DISABLE_AUDIT_LOG = 'disable-audit-log';

    /**
     * @var EntityWriterInterface
     */
    private $entityWriter;

    /**
     * @var EntityReaderInterface
     */
    private $entityReader;

    /**
     * @var EntitySearcherInterface
     */
    private $entitySearcher;

    /**
     * @var EntityWriteGatewayInterface
     */
    private $entityWriteGateway;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var VersionCommitDefinition
     */
    private $versionCommitDefinition;

    /**
     * @var VersionCommitDataDefinition
     */
    private $versionCommitDataDefinition;

    /**
     * @var VersionDefinition
     */
    private $versionDefinition;

    /**
     * @var DefinitionInstanceRegistry
     */
    private $registry;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(
        EntityWriterInterface $entityWriter,
        EntityReaderInterface $entityReader,
        EntitySearcherInterface $entitySearcher,
        EntityWriteGatewayInterface $entityWriteGateway,
        EventDispatcherInterface $eventDispatcher,
        SerializerInterface $serializer,
        DefinitionInstanceRegistry $registry,
        VersionCommitDefinition $versionCommitDefinition,
        VersionCommitDataDefinition $versionCommitDataDefinition,
        VersionDefinition $versionDefinition,
        Connection $connection
    ) {
        $this->entityWriter = $entityWriter;
        $this->entityReader = $entityReader;
        $this->entitySearcher = $entitySearcher;
        $this->entityWriteGateway = $entityWriteGateway;
        $this->eventDispatcher = $eventDispatcher;
        $this->serializer = $serializer;
        $this->versionCommitDefinition = $versionCommitDefinition;
        $this->versionCommitDataDefinition = $versionCommitDataDefinition;
        $this->versionDefinition = $versionDefinition;
        $this->registry = $registry;
        $this->connection = $connection;
    }

    public function upsert(EntityDefinition $definition, array $rawData, WriteContext $writeContext): array
    {
        $writeResults = $this->entityWriter->upsert($definition, $rawData, $writeContext);

        $mappings = $this->resolveRelations($definition, $rawData, $writeResults);

        $writeResults = $this->addParentResults($writeResults, $mappings);

        $this->writeAuditLog($writeResults, $writeContext);

        return $writeResults;
    }

    public function insert(EntityDefinition $definition, array $rawData, WriteContext $writeContext): array
    {
        /** @var EntityWriteResult[] $writeResults */
        $writeResults = $this->entityWriter->insert($definition, $rawData, $writeContext);

        $mappings = $this->resolveRelations($definition, $rawData, $writeResults);

        $writeResults = $this->addParentResults($writeResults, $mappings);

        $this->writeAuditLog($writeResults, $writeContext);

        return $writeResults;
    }

    public function update(EntityDefinition $definition, array $rawData, WriteContext $writeContext): array
    {
        $writeResults = $this->entityWriter->update($definition, $rawData, $writeContext);

        $mappings = $this->resolveRelations($definition, $rawData, $writeResults);

        $writeResults = $this->addParentResults($writeResults, $mappings);

        $this->writeAuditLog($writeResults, $writeContext);

        return $writeResults;
    }

    public function delete(EntityDefinition $definition, array $ids, WriteContext $writeContext): DeleteResult
    {
        $parents = $this->resolveParents($definition, $ids);

        $deleteEvent = $this->entityWriter->delete($definition, $ids, $writeContext);

        $deleted = $deleteEvent->getDeleted();

        $deleted = $this->addParentResults($deleted, $parents);

        $this->writeAuditLog($deleted, $writeContext);

        if (!$parents) {
            return $deleteEvent;
        }

        $updates = [];

        foreach ($deleted as $entity => $results) {
            $updates[$entity] = array_filter($results, function (EntityWriteResult $result) {
                return $result->getOperation() === EntityWriteResult::OPERATION_UPDATE;
            });
        }

        $deleteEvent->addUpdated(array_filter($updates));

        return $deleteEvent;
    }

    public function createVersion(EntityDefinition $definition, string $id, WriteContext $context, ?string $name = null, ?string $versionId = null): string
    {
        $primaryKey = [
            'id' => $id,
            'versionId' => Defaults::LIVE_VERSION,
        ];

        $versionId = $versionId ?? Uuid::randomHex();
        $versionData = ['id' => $versionId];

        if ($name) {
            $versionData['name'] = $name;
        }

        $context->scope(Context::SYSTEM_SCOPE, function ($context) use ($versionData): void {
            $this->entityWriter->upsert($this->versionDefinition, [$versionData], $context);
        });

        $affected = $this->cloneEntity($definition, $primaryKey['id'], $primaryKey['id'], $versionId, $context, new CloneBehavior(), false);

        $versionContext = $context->createWithVersionId($versionId);

        $event = EntityWrittenContainerEvent::createWithWrittenEvents($affected, $versionContext->getContext(), []);
        $this->eventDispatcher->dispatch($event);

        $this->writeAuditLog($affected, $context, $versionId, true);

        return $versionId;
    }

    public function merge(string $versionId, WriteContext $writeContext): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('version_commit.versionId', $versionId));
        $criteria->addSorting(new FieldSorting('version_commit.autoIncrement'));
        $commitIds = $this->entitySearcher->search($this->versionCommitDefinition, $criteria, $writeContext->getContext());

        $readCriteria = new Criteria($commitIds->getIds());
        $readCriteria->addAssociation('data');

        $readCriteria
            ->getAssociation('data')
            ->addSorting(new FieldSorting('autoIncrement'));

        $commits = $this->entityReader->read($this->versionCommitDefinition, $readCriteria, $writeContext->getContext());

        $allChanges = [];
        $entities = [];

        $versionContext = $writeContext->createWithVersionId($versionId);
        $liveContext = $writeContext->createWithVersionId(Defaults::LIVE_VERSION);

        $writtenEvents = [];
        $deletedEvents = [];

        // merge all commits into a single write operation
        foreach ($commits as $commit) {
            foreach ($commit->getData() as $data) {
                $dataDefinition = $this->registry->getByEntityName($data->getEntityName());

                // skip clone action, otherwise the payload would contain all data
                if ($data->getAction() !== 'clone') {
                    $allChanges[] = $data;
                }

                $entity = [
                    'definition' => $dataDefinition,
                    'primary' => $data->getEntityId(),
                ];

                // deduplicate to prevent deletion errors
                $entityKey = md5(JsonFieldSerializer::encodeJson($entity));
                $entities[$entityKey] = $entity;

                if (empty($data->getPayload()) && $data->getAction() !== 'delete') {
                    continue;
                }

                switch ($data->getAction()) {
                    case 'insert':
                    case 'update':
                    case 'upsert':
                        if ($dataDefinition instanceof EntityTranslationDefinition && $this->translationHasParent($commit, $data)) {
                            break;
                        }

                        $payload = $this->addVersionToPayload($data->getPayload(), $dataDefinition, Defaults::LIVE_VERSION);

                        $payload = $this->addTranslationToPayload($data->getEntityId(), $payload, $dataDefinition, $commit);

                        $events = $this->entityWriter->upsert($dataDefinition, [$payload], $liveContext);

                        $writtenEvents = array_merge_recursive($writtenEvents, $events);

                        break;

                    case 'delete':
                        $id = $data->getEntityId();
                        $id = $this->addVersionToPayload($id, $dataDefinition, Defaults::LIVE_VERSION);

                        $deletedEvents[] = $this->entityWriter->delete($dataDefinition, [$id], $liveContext);

                        break;
                }
            }

            $this->entityWriter->delete($this->versionCommitDefinition, [['id' => $commit->getId()]], $liveContext);
        }

        $newData = array_map(function (VersionCommitDataEntity $data) {
            $definition = $this->registry->getByEntityName($data->getEntityName());

            $id = $data->getEntityId();
            $id = $this->addVersionToPayload($id, $definition, Defaults::LIVE_VERSION);

            $payload = $this->addVersionToPayload($data->getPayload(), $definition, Defaults::LIVE_VERSION);

            return [
                'entityId' => $id,
                'payload' => JsonFieldSerializer::encodeJson($payload),
                'userId' => $data->getUserId(),
                'integrationId' => $data->getIntegrationId(),
                'entityName' => $data->getEntityName(),
                'action' => $data->getAction(),
                'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ];
        }, $allChanges);

        $commit = [
            'versionId' => Defaults::LIVE_VERSION,
            'data' => $newData,
            'userId' => $writeContext->getContext()->getSource() instanceof AdminApiSource ? $writeContext->getContext()->getSource()->getUserId() : null,
            'isMerge' => true,
            'message' => 'merge commit ' . (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        // create new version commit for merge commit
        $this->entityWriter->insert($this->versionCommitDefinition, [$commit], $writeContext);

        // delete version
        $this->entityWriter->delete($this->versionDefinition, [['id' => $versionId]], $writeContext);

        foreach ($entities as $entity) {
            /** @var EntityDefinition|string $definition */
            $definition = $entity['definition'];
            $primary = $entity['primary'];
            $primary = $this->addVersionToPayload($primary, $definition, $versionId);

            $this->entityWriter->delete($definition, [$primary], $versionContext);
        }

        $event = EntityWrittenContainerEvent::createWithWrittenEvents($writtenEvents, $liveContext->getContext(), []);
        $this->eventDispatcher->dispatch($event);

        foreach ($deletedEvents as $deletedEvent) {
            $event = EntityWrittenContainerEvent::createWithDeletedEvents($deletedEvent->getDeleted(), $liveContext->getContext(), $deletedEvent->getNotFound());
            $this->eventDispatcher->dispatch($event);
        }
    }

    public function clone(
        EntityDefinition $definition,
        string $id,
        string $newId,
        string $versionId,
        WriteContext $context,
        CloneBehavior $behavior
    ): array {
        return $this->cloneEntity($definition, $id, $newId, $versionId, $context, $behavior, true);
    }

    private function cloneEntity(
        EntityDefinition $definition,
        string $id,
        string $newId,
        string $versionId,
        WriteContext $context,
        CloneBehavior $behavior,
        bool $writeAuditLog = false
    ): array {
        $criteria = new Criteria([$id]);
        $this->addCloneAssociations($definition, $criteria, $behavior->cloneChildren());

        $detail = $this->entityReader->read($definition, $criteria, $context->getContext())->first();

        if ($detail === null) {
            throw new \RuntimeException(sprintf('Cannot create new version. %s by id (%s) not found.', $definition->getEntityName(), $id));
        }

        $data = json_decode($this->serializer->serialize($detail, 'json'), true);

        $keepIds = $newId === $id;

        $data = $this->filterPropertiesForClone($definition, $data, $keepIds, $id, $definition, $context->getContext());
        $data['id'] = $newId;

        $data = array_replace_recursive($data, $behavior->getOverwrites());

        $versionContext = $context->createWithVersionId($versionId);
        $result = null;
        $versionContext->scope(Context::SYSTEM_SCOPE, function (WriteContext $context) use ($definition, $data, &$result): void {
            $result = $this->entityWriter->insert($definition, [$data], $context);
        });

        if ($writeAuditLog) {
            $this->writeAuditLog($result, $versionContext);
        }

        return $result;
    }

    private function filterPropertiesForClone(EntityDefinition $definition, array $data, bool $keepIds, string $cloneId, EntityDefinition $cloneDefinition, Context $context): array
    {
        $extensions = [];
        $payload = [];

        $fields = $definition->getFields();

        foreach ($fields as $field) {
            /** @var WriteProtected|null $writeProtection */
            $writeProtection = $field->getFlag(WriteProtected::class);
            if ($writeProtection && !$writeProtection->isAllowed(Context::SYSTEM_SCOPE)) {
                continue;
            }

            //set data and payload cursor to root or extensions to simplify following if conditions
            $dataCursor = $data;

            $payloadCursor = &$payload;

            if ($field instanceof VersionField || $field instanceof ReferenceVersionField) {
                continue;
            }

            if ($field->is(Extension::class)) {
                $dataCursor = $data['extensions'] ?? [];
                $payloadCursor = &$extensions;
            }

            if (!\array_key_exists($field->getPropertyName(), $dataCursor)) {
                continue;
            }

            if (!$keepIds && $field instanceof ParentFkField) {
                continue;
            }

            $value = $dataCursor[$field->getPropertyName()];

            // remove reference of cloned entity in all sub entity routes. Appears in a parent-child nested data tree
            if ($field instanceof FkField && !$keepIds && $value === $cloneId && $cloneDefinition === $field->getReferenceDefinition()) {
                continue;
            }

            if ($value === null) {
                continue;
            }

            //scalar value? assign directly
            if (!$field instanceof AssociationField) {
                $payloadCursor[$field->getPropertyName()] = $value;

                continue;
            }

            //many to one should be skipped because it is no part of the root entity
            if ($field instanceof ManyToOneAssociationField) {
                continue;
            }

            /** @var CascadeDelete|null $flag */
            $flag = $field->getFlag(CascadeDelete::class);
            if (!$flag || !$flag->isCloneRelevant()) {
                continue;
            }

            if ($field instanceof OneToManyAssociationField) {
                $reference = $field->getReferenceDefinition();

                $nested = [];
                foreach ($value as $item) {
                    $nestedItem = $this->filterPropertiesForClone($reference, $item, $keepIds, $cloneId, $cloneDefinition, $context);

                    if (!$keepIds) {
                        $nestedItem = $this->removePrimaryKey($field, $nestedItem);
                    }

                    $nested[] = $nestedItem;
                }

                $nested = array_filter($nested);
                if (empty($nested)) {
                    continue;
                }

                $payloadCursor[$field->getPropertyName()] = $nested;

                continue;
            }

            if ($field instanceof ManyToManyAssociationField) {
                $nested = [];

                foreach ($value as $item) {
                    $nested[] = ['id' => $item['id']];
                }
                $nested = array_filter($nested);

                if (empty($nested)) {
                    continue;
                }

                $payloadCursor[$field->getPropertyName()] = $nested;

                continue;
            }

            if ($field instanceof OneToOneAssociationField && $value) {
                $reference = $field->getReferenceDefinition();

                $nestedItem = $this->filterPropertiesForClone($reference, $value, $keepIds, $cloneId, $cloneDefinition, $context);

                if (!$keepIds) {
                    $nestedItem = $this->removePrimaryKey($field, $nestedItem);
                }

                $payloadCursor[$field->getPropertyName()] = $nestedItem;
            }
        }

        if (!empty($extensions)) {
            $payload['extensions'] = $extensions;
        }

        return $payload;
    }

    private function writeAuditLog(array $writtenEvents, WriteContext $writeContext, ?string $versionId = null, bool $isClone = false): void
    {
        if ($writeContext->getContext()->hasState(self::DISABLE_AUDIT_LOG)) {
            return;
        }

        $versionId = $versionId ?? $writeContext->getContext()->getVersionId();
        if ($versionId === Defaults::LIVE_VERSION) {
            return;
        }

        $commitId = Uuid::randomBytes();

        $date = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $source = $writeContext->getContext()->getSource();
        $userId = $source instanceof AdminApiSource && $source->getUserId()
            ? Uuid::fromHexToBytes($source->getUserId())
            : null;

        $insert = new InsertCommand(
            $this->versionCommitDefinition,
            [
                'id' => $commitId,
                'user_id' => $userId,
                'version_id' => Uuid::fromHexToBytes($versionId),
                'created_at' => $date,
            ],
            ['id' => $commitId],
            new EntityExistence(
                $this->versionCommitDefinition->getEntityName(),
                ['id' => Uuid::fromBytesToHex($commitId)],
                false,
                false,
                false,
                []
            ),
            ''
        );

        $commands = [$insert];

        foreach ($writtenEvents as $items) {
            if (\count($items) === 0) {
                continue;
            }

            $definition = $this->registry->getByEntityName($items[0]->getEntityName());
            $entityName = $definition->getEntityName();

            if (!$definition->isVersionAware()) {
                continue;
            }

            if (mb_strpos('version', $entityName) === 0) {
                continue;
            }

            /** @var EntityWriteResult $item */
            foreach ($items as $item) {
                $payload = $item->getPayload();

                $primary = $item->getPrimaryKey();
                if (!\is_array($primary)) {
                    $primary = ['id' => $primary];
                }
                $primary['versionId'] = $versionId;

                $id = Uuid::randomBytes();

                $commands[] = new InsertCommand(
                    $this->versionCommitDataDefinition,
                    [
                        'id' => $id,
                        'version_commit_id' => $commitId,
                        'entity_name' => $entityName,
                        'entity_id' => JsonFieldSerializer::encodeJson($primary),
                        'payload' => JsonFieldSerializer::encodeJson($payload),
                        'user_id' => $userId,
                        'action' => $isClone ? 'clone' : $item->getOperation(),
                        'created_at' => $date,
                    ],
                    ['id' => $id],
                    new EntityExistence(
                        $this->versionCommitDataDefinition->getEntityName(),
                        ['id' => Uuid::fromBytesToHex($id)],
                        false,
                        false,
                        false,
                        []
                    ),
                    ''
                );
            }
        }

        if (\count($commands) <= 1) {
            return;
        }

        $writeContext->scope(Context::SYSTEM_SCOPE, function () use ($commands, $writeContext): void {
            $this->entityWriteGateway->execute($commands, $writeContext);
        });
    }

    private function addVersionToPayload(array $payload, EntityDefinition $definition, string $versionId): array
    {
        $fields = $definition->getFields()->filter(function (Field $field) {
            return $field instanceof VersionField || $field instanceof ReferenceVersionField;
        });

        foreach ($fields as $field) {
            $payload[$field->getPropertyName()] = $versionId;
        }

        return $payload;
    }

    private function removePrimaryKey(AssociationField $field, array $nestedItem): array
    {
        $pkFields = $field->getReferenceDefinition()->getPrimaryKeys();

        /** @var Field|StorageAware $pkField */
        foreach ($pkFields as $pkField) {
            /*
             * `EntityTranslationDefinition`s dont have an `id`, they use a composite primary key consisting of the
             * entity id and the `languageId`. When cloning the entity we want to copy the `languageId`. The entity id
             * has to be unset, so that its set by the parent, resulting in a valid primary key.
             */
            if ($field instanceof TranslationsAssociationField && $pkField->getStorageName() === $field->getLanguageField()) {
                continue;
            }
            if (\array_key_exists($pkField->getPropertyName(), $nestedItem)) {
                unset($nestedItem[$pkField->getPropertyName()]);
            }
        }

        return $nestedItem;
    }

    private function addCloneAssociations(
        EntityDefinition $definition,
        Criteria $criteria,
        bool $cloneChildren,
        int $childCounter = 1
    ): void {
        //add all cascade delete associations
        $cascades = $definition->getFields()->filter(function (Field $field) {
            /** @var CascadeDelete|null $flag */
            $flag = $field->getFlag(CascadeDelete::class);

            return $flag ? $flag->isCloneRelevant() : false;
        });

        /** @var AssociationField $cascade */
        foreach ($cascades as $cascade) {
            $nested = $criteria->getAssociation($cascade->getPropertyName());

            if ($cascade instanceof ManyToManyAssociationField) {
                continue;
            }

            //many to one shouldn't be cascaded
            if ($cascade instanceof ManyToOneAssociationField) {
                continue;
            }

            $reference = $cascade->getReferenceDefinition();

            $childrenAware = $reference->isChildrenAware();

            //first level of parent-child tree?
            if ($childrenAware && $reference !== $definition) {
                //where product.children.parentId IS NULL
                $nested->addFilter(new EqualsFilter($reference->getEntityName() . '.parentId', null));
            }

            if ($cascade instanceof ChildrenAssociationField) {
                //break endless loop
                if ($childCounter >= 30 || !$cloneChildren) {
                    $criteria->removeAssociation($cascade->getPropertyName());

                    continue;
                }

                ++$childCounter;
                $this->addCloneAssociations($reference, $nested, $cloneChildren, $childCounter);

                continue;
            }

            $this->addCloneAssociations($reference, $nested, $cloneChildren);
        }
    }

    private function addParentResults(array $writeResults, array $parents): array
    {
        foreach ($parents as $entity => $primaryKeys) {
            $primaryKeys = array_unique($primaryKeys);
            if (!isset($writeResults[$entity])) {
                $writeResults[$entity] = [];
            }

            foreach ($primaryKeys as $primaryKey) {
                if ($this->hasResult($entity, $primaryKey, $writeResults)) {
                    continue;
                }
                $writeResults[$entity][] = new EntityWriteResult($primaryKey, [], $entity, EntityWriteResult::OPERATION_UPDATE);
            }
        }

        return $writeResults;
    }

    /**
     * @param string|array          $primaryKey
     * @param EntityWriteResult[][] $results
     */
    private function hasResult(string $entity, $primaryKey, array $results): bool
    {
        if (!isset($results[$entity])) {
            return false;
        }

        foreach ($results[$entity] as $result) {
            if ($result->getPrimaryKey() === $primaryKey) {
                return true;
            }
        }

        return false;
    }

    private function resolveParents(EntityDefinition $definition, array $rawData): array
    {
        if ($definition instanceof MappingEntityDefinition) {
            return $this->resolveMappingParents($definition, $rawData);
        }

        $parentIds = [];
        if ($definition->isInheritanceAware()) {
            $parentIds = $this->fetchParentIds($definition, $rawData);
        }

        $parent = $definition->getParentDefinition();

        if (!$parent) {
            return $parentIds;
        }

        $fkField = $definition->getFields()->filter(function (Field $field) use ($parent) {
            if (!$field instanceof FkField || $field instanceof ReferenceVersionField) {
                return false;
            }

            return $field->getReferenceDefinition()->getClass() === $parent->getClass();
        });

        $fkField = $fkField->first();
        if (!$fkField instanceof FkField) {
            throw new \RuntimeException(sprintf('Can not detect foreign key for parent definition %s', $parent->getClass()));
        }

        $primaryKeys = $this->getPrimaryKeysOfFkField($definition, $rawData, $fkField);

        $mapped = array_map(function ($id) {
            return ['id' => $id];
        }, $primaryKeys);

        $nested = $this->resolveParents($parent, $mapped);

        $entity = $parent->getEntityName();

        $nested[$entity] = array_merge($nested[$entity] ?? [], $primaryKeys, $parentIds[$entity] ?? []);

        return $nested;
    }

    private function resolveMappingParents(EntityDefinition $definition, array $rawData): array
    {
        $fkFields = $definition->getFields()->filter(function (Field $field) {
            return $field instanceof FkField && !$field instanceof ReferenceVersionField;
        });

        $mapping = [];

        /** @var FkField $fkField */
        foreach ($fkFields as $fkField) {
            $primaryKeys = $this->getPrimaryKeysOfFkField($definition, $rawData, $fkField);

            $entity = $fkField->getReferenceDefinition()->getEntityName();
            $mapping[$entity] = array_merge($mapping[$entity] ?? [], $primaryKeys);

            $mapped = array_map(function ($id) {
                return ['id' => $id];
            }, $primaryKeys);

            $nested = $this->resolveParents($fkField->getReferenceDefinition(), $mapped);

            foreach ($nested as $entity => $primaryKeys) {
                $mapping[$entity] = array_merge($mapping[$entity] ?? [], $primaryKeys);
            }
        }

        return $mapping;
    }

    private function fetchForeignKey(EntityDefinition $definition, array $rawData, FkField $fkField): string
    {
        $query = $this->connection->createQueryBuilder();
        $query->select(
            'LOWER(HEX(' . EntityDefinitionQueryHelper::escape($fkField->getStorageName()) . '))'
        );
        $query->from(EntityDefinitionQueryHelper::escape($definition->getEntityName()));

        foreach ($definition->getPrimaryKeys() as $index => $primaryKey) {
            $property = $primaryKey->getPropertyName();

            if ($primaryKey instanceof VersionField || $primaryKey instanceof ReferenceVersionField) {
                continue;
            }

            if (!isset($rawData[$property])) {
                throw new \RuntimeException(
                    sprintf('Missing primary key %s for definition %s', $property, $definition->getClass())
                );
            }
            if (!$primaryKey instanceof StorageAware) {
                continue;
            }
            $key = 'primaryKey' . $index;

            $query->andWhere(
                EntityDefinitionQueryHelper::escape($primaryKey->getStorageName()) . ' = :' . $key
            );

            $query->setParameter($key, Uuid::fromHexToBytes($rawData[$property]));
        }

        $fk = $query->execute()->fetchColumn();

        if (!$fk) {
            throw new \RuntimeException('Fk can not be detected');
        }

        return (string) $fk;
    }

    private function getPrimaryKeysOfFkField(EntityDefinition $definition, array $rawData, FkField $fkField): array
    {
        $parent = $fkField->getReferenceDefinition();

        $referenceField = $parent->getFields()->getByStorageName($fkField->getReferenceField());
        if (!$referenceField) {
            throw new \RuntimeException(
                sprintf(
                    'Can not detect reference field with storage name %s in definition %s',
                    $fkField->getReferenceField(),
                    $parent->getClass()
                )
            );
        }

        $primaryKeys = [];
        foreach ($rawData as $row) {
            if (\array_key_exists($fkField->getPropertyName(), $row)) {
                $fk = $row[$fkField->getPropertyName()];
            } else {
                $fk = $this->fetchForeignKey($definition, $row, $fkField);
            }

            $primaryKeys[] = $fk;
        }

        return $primaryKeys;
    }

    private function translationHasParent(VersionCommitEntity $commit, VersionCommitDataEntity $translationData): bool
    {
        $translationDefinition = $this->registry->getByEntityName($translationData->getEntityName());

        $parentEntity = $translationDefinition->getParentDefinition()->getEntityName();

        $parentPropertyName = $this->getEntityForeignKeyName($parentEntity);

        $parentId = $translationData->getPayload()[$parentPropertyName];

        foreach ($commit->getData() as $data) {
            if ($data->getEntityName() !== $parentEntity) {
                continue;
            }

            $primary = $data->getEntityId();

            if (!isset($primary['id'])) {
                continue;
            }

            if ($primary['id'] === $parentId) {
                return true;
            }
        }

        return false;
    }

    private function addTranslationToPayload(array $entityId, array $payload, EntityDefinition $definition, VersionCommitEntity $commit): array
    {
        $translationDefinition = $definition->getTranslationDefinition();

        if (!$translationDefinition) {
            return $payload;
        }
        if (!isset($entityId['id'])) {
            return $payload;
        }

        $id = $entityId['id'];

        $translations = [];

        $foreignKeyName = $this->getEntityForeignKeyName($definition->getEntityName());

        foreach ($commit->getData() as $data) {
            if ($data->getEntityName() !== $translationDefinition->getEntityName()) {
                continue;
            }

            $translation = $data->getPayload();
            if (!isset($translation[$foreignKeyName])) {
                continue;
            }

            if ($translation[$foreignKeyName] !== $id) {
                continue;
            }

            $translations[] = $this->addVersionToPayload($translation, $translationDefinition, Defaults::LIVE_VERSION);
        }

        $payload['translations'] = $translations;

        return $payload;
    }

    private function getEntityForeignKeyName(string $parentEntity): string
    {
        $parentPropertyName = explode('_', $parentEntity);
        $parentPropertyName = array_map('ucfirst', $parentPropertyName);

        return lcfirst(implode('', $parentPropertyName)) . 'Id';
    }

    private function resolveRelations(EntityDefinition $definition, array $rawData, array $writeResults): array
    {
        $parents = $this->resolveParents($definition, $rawData);

        /** @var EntityWriteResult[] $result */
        foreach ($writeResults as $entity => $result) {
            $definition = $this->registry->getByEntityName($entity);

            if (!$definition instanceof MappingEntityDefinition) {
                continue;
            }

            $ids = array_map(function (EntityWriteResult $result) {
                return $result->getPrimaryKey();
            }, $result);

            if (empty($ids)) {
                continue;
            }

            $fkFields = $definition->getFields()->filterInstance(FkField::class);

            if ($fkFields->count() <= 0) {
                continue;
            }

            /** @var FkField $field */
            foreach ($fkFields as $field) {
                $reference = $field->getReferenceDefinition()->getEntityName();

                $parents[$reference] = array_merge($parents[$reference] ?? [], array_column($ids, $field->getPropertyName()));
            }
        }

        return $parents;
    }

    private function fetchParentIds(EntityDefinition $definition, array $rawData): array
    {
        $fetchQuery = sprintf(
            'SELECT DISTINCT LOWER(HEX(parent_id)) as id FROM %s WHERE id IN (:ids)',
            EntityDefinitionQueryHelper::escape($definition->getEntityName())
        );

        $parentIds = $this->connection->fetchAll(
            $fetchQuery,
            ['ids' => Uuid::fromHexToBytesList(array_column($rawData, 'id'))],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        $ids = array_unique(array_filter(array_column($parentIds, 'id')));

        if (\count($ids) === 0) {
            return [];
        }

        return [$definition->getEntityName() => $ids];
    }
}
