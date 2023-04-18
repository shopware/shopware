<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\VersionMergeAlreadyLockedException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
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
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommit\VersionCommitCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommit\VersionCommitDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommit\VersionCommitEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommitData\VersionCommitDataDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommitData\VersionCommitDataEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Version\VersionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\CloneBehavior;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @internal
 */
#[Package('core')]
class VersionManager
{
    final public const DISABLE_AUDIT_LOG = 'disable-audit-log';
    final public const MERGE_SCOPE = 'merge-scope';

    public function __construct(
        private readonly EntityWriterInterface $entityWriter,
        private readonly EntityReaderInterface $entityReader,
        private readonly EntitySearcherInterface $entitySearcher,
        private readonly EntityWriteGatewayInterface $entityWriteGateway,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly SerializerInterface $serializer,
        private readonly DefinitionInstanceRegistry $registry,
        private readonly VersionCommitDefinition $versionCommitDefinition,
        private readonly VersionCommitDataDefinition $versionCommitDataDefinition,
        private readonly VersionDefinition $versionDefinition,
        private readonly LockFactory $lockFactory
    ) {
    }

    /**
     * @param array<array<string, mixed|null>> $rawData
     *
     * @return array<string, array<EntityWriteResult>>
     */
    public function upsert(EntityDefinition $definition, array $rawData, WriteContext $writeContext): array
    {
        $result = $this->entityWriter->upsert($definition, $rawData, $writeContext);

        $this->writeAuditLog($result, $writeContext);

        return $result;
    }

    /**
     * @param array<array<string, mixed|null>> $rawData
     *
     * @return array<string, array<EntityWriteResult>>
     */
    public function insert(EntityDefinition $definition, array $rawData, WriteContext $writeContext): array
    {
        /** @var array<string, array<EntityWriteResult>> $result */
        $result = $this->entityWriter->insert($definition, $rawData, $writeContext);

        $this->writeAuditLog($result, $writeContext);

        return $result;
    }

    /**
     * @param array<array<string, mixed|null>> $rawData
     *
     * @return array<string, array<EntityWriteResult>>
     */
    public function update(EntityDefinition $definition, array $rawData, WriteContext $writeContext): array
    {
        /** @var array<string, array<EntityWriteResult>> $result */
        $result = $this->entityWriter->update($definition, $rawData, $writeContext);

        $this->writeAuditLog($result, $writeContext);

        return $result;
    }

    /**
     * @param array<array<string, mixed|null>> $ids
     */
    public function delete(EntityDefinition $definition, array $ids, WriteContext $writeContext): WriteResult
    {
        $result = $this->entityWriter->delete($definition, $ids, $writeContext);

        $this->writeAuditLog($result->getDeleted(), $writeContext);

        return $result;
    }

    public function createVersion(EntityDefinition $definition, string $id, WriteContext $context, ?string $name = null, ?string $versionId = null): string
    {
        $versionId = $versionId ?? Uuid::randomHex();
        $versionData = ['id' => $versionId];

        if ($name) {
            $versionData['name'] = $name;
        }

        $context->scope(Context::SYSTEM_SCOPE, function ($context) use ($versionData): void {
            $this->entityWriter->upsert($this->versionDefinition, [$versionData], $context);
        });

        $affected = $this->cloneEntity($definition, $id, $id, $versionId, $context, new CloneBehavior());

        $versionContext = $context->createWithVersionId($versionId);

        $event = EntityWrittenContainerEvent::createWithWrittenEvents($affected, $versionContext->getContext(), []);
        $this->eventDispatcher->dispatch($event);

        $this->writeAuditLog($affected, $context, $versionId, true);

        return $versionId;
    }

    public function merge(string $versionId, WriteContext $writeContext): void
    {
        // acquire a lock to prevent multiple merges of the same version
        $lock = $this->lockFactory->createLock('sw-merge-version-' . $versionId);

        if (!$lock->acquire()) {
            throw new VersionMergeAlreadyLockedException($versionId);
        }

        // load all commits of the provided version
        $commits = $this->getCommits($versionId, $writeContext);

        // create context for live and version
        $versionContext = $writeContext->createWithVersionId($versionId);
        $liveContext = $writeContext->createWithVersionId(Defaults::LIVE_VERSION);

        $versionContext->addState(self::MERGE_SCOPE);
        $liveContext->addState(self::MERGE_SCOPE);

        // group all payloads by their action (insert, update, delete) and by their entity name
        $writes = $this->buildWrites($commits);

        // execute writes and get access to the write result to dispatch events later on
        $result = $this->executeWrites($writes, $liveContext);

        // remove commits which reference the version and create a "merge commit" for the live version with all payloads
        $this->updateVersionData($commits, $writeContext, $versionId);

        // delete all versioned records
        $this->deleteClones($commits, $versionContext, $versionId);

        // release lock to ensure no other merge is running
        $lock->release();

        // dispatch events to trigger indexer and other subscribts
        $writes = EntityWrittenContainerEvent::createWithWrittenEvents($result->getWritten(), $liveContext->getContext(), []);

        $deletes = EntityWrittenContainerEvent::createWithDeletedEvents($result->getDeleted(), $liveContext->getContext(), []);

        if ($deletes->getEvents() !== null) {
            $writes->addEvent(...$deletes->getEvents()->getElements());
        }
        $this->eventDispatcher->dispatch($writes);

        $versionContext->removeState(self::MERGE_SCOPE);
        $liveContext->addState(self::MERGE_SCOPE);
    }

    /**
     * @return array<string, array<EntityWriteResult>>
     */
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

    /**
     * @return array<string, array<EntityWriteResult>>
     */
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

        $data = json_decode($this->serializer->serialize($detail, 'json'), true, 512, \JSON_THROW_ON_ERROR);

        $keepIds = $newId === $id;

        $data = $this->filterPropertiesForClone($definition, $data, $keepIds, $id, $definition, $context->getContext());
        $data['id'] = $newId;

        $createdAtField = $definition->getField('createdAt');
        $updatedAtField = $definition->getField('updatedAt');

        if ($createdAtField instanceof DateTimeField) {
            $data['createdAt'] = new \DateTime();
        }

        if ($updatedAtField instanceof DateTimeField) {
            if ($updatedAtField->getFlag(Required::class)) {
                $data['updatedAt'] = new \DateTime();
            } else {
                $data['updatedAt'] = null;
            }
        }

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

    /**
     * @param array<string, array<string, mixed|null>|null> $data
     *
     * @return array<string, array<string, mixed|null>|string|null>
     */
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
                if (isset($dataCursor['foreignKeys'])) {
                    $fields = $definition->getFields();
                    /**
                     * @var string $key
                     * @var string $value
                     */
                    foreach ($dataCursor['foreignKeys'] as $key => $value) {
                        // Clone FK extension and add it to payload
                        if (\is_string($value) && Uuid::isValid($value) && $fields->has($key) && $fields->get($key) instanceof FkField) {
                            $payload[$key] = $value;
                        }
                    }
                }
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

    /**
     * @param array<string, array<EntityWriteResult>> $writtenEvents
     */
    private function writeAuditLog(array $writtenEvents, WriteContext $writeContext, ?string $versionId = null, bool $isClone = false): void
    {
        if ($writeContext->getContext()->hasState(self::DISABLE_AUDIT_LOG)) {
            return;
        }

        $versionId ??= $writeContext->getContext()->getVersionId();
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
            EntityExistence::createForEntity(
                $this->versionCommitDefinition->getEntityName(),
                ['id' => Uuid::fromBytesToHex($commitId)],
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
                        'entity_id' => Json::encode($primary),
                        'payload' => Json::encode($payload),
                        'user_id' => $userId,
                        'action' => $isClone ? 'clone' : $item->getOperation(),
                        'created_at' => $date,
                    ],
                    ['id' => $id],
                    EntityExistence::createForEntity(
                        $this->versionCommitDataDefinition->getEntityName(),
                        ['id' => Uuid::fromBytesToHex($id)],
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

    /**
     * @param array<string, array<string, mixed>|string|null> $payload
     *
     * @return array<string, array<string, mixed>|string|null>
     */
    private function addVersionToPayload(array $payload, EntityDefinition $definition, string $versionId): array
    {
        $fields = $definition->getFields()->filter(fn (Field $field) => $field instanceof VersionField || $field instanceof ReferenceVersionField);

        foreach ($fields as $field) {
            $payload[$field->getPropertyName()] = $versionId;
        }

        return $payload;
    }

    /**
     * @param array<string, array<string, mixed>|string|null> $nestedItem
     *
     * @return array<string, array<string, mixed>|string|null>
     */
    private function removePrimaryKey(AssociationField $field, array $nestedItem): array
    {
        $pkFields = $field->getReferenceDefinition()->getPrimaryKeys();

        foreach ($pkFields as $pkField) {
            /*
             * `EntityTranslationDefinition`s dont have an `id`, they use a composite primary key consisting of the
             * entity id and the `languageId`. When cloning the entity we want to copy the `languageId`. The entity id
             * has to be unset, so that its set by the parent, resulting in a valid primary key.
             */
            /** @var StorageAware $pkField */
            if ($field instanceof TranslationsAssociationField && $pkField->getStorageName() === $field->getLanguageField()) {
                continue;
            }
            /** @var Field $pkField */
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

    private function translationHasParent(VersionCommitEntity $commit, VersionCommitDataEntity $translationData): bool
    {
        /** @var EntityTranslationDefinition $translationDefinition */
        $translationDefinition = $this->registry->getByEntityName($translationData->getEntityName());

        $parentEntity = $translationDefinition->getParentDefinition()->getEntityName();

        $parentPropertyName = $this->getEntityForeignKeyName($parentEntity);

        /** @var array<string, string> $payload */
        $payload = $translationData->getPayload();
        $parentId = $payload[$parentPropertyName];

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

    /**
     * @param array<string> $entityId
     * @param array<string|int, mixed> $payload
     *
     * @return array<string|int, mixed>
     */
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

    private function getCommits(string $versionId, WriteContext $writeContext): VersionCommitCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('version_commit.versionId', $versionId));
        $criteria->addSorting(new FieldSorting('version_commit.autoIncrement'));
        $commitIds = $this->entitySearcher->search($this->versionCommitDefinition, $criteria, $writeContext->getContext());

        $readCriteria = new Criteria();
        if ($commitIds->getTotal() > 0) {
            $readCriteria = new Criteria($commitIds->getIds());
        }

        $readCriteria->addAssociation('data');

        $readCriteria
            ->getAssociation('data')
            ->addSorting(new FieldSorting('autoIncrement'));

        /** @var VersionCommitCollection $commits */
        $commits = $this->entityReader->read($this->versionCommitDefinition, $readCriteria, $writeContext->getContext());

        return $commits;
    }

    /**
     * @return array{insert:array<string, array<int, mixed>>, update:array<string, array<int, mixed>>, delete:array<string, array<int, mixed>>}
     */
    private function buildWrites(VersionCommitCollection $commits): array
    {
        $writes = [
            'insert' => [],
            'update' => [],
            'delete' => [],
        ];

        foreach ($commits as $commit) {
            foreach ($commit->getData() as $data) {
                $definition = $this->registry->getByEntityName($data->getEntityName());

                switch ($data->getAction()) {
                    case 'insert':
                    case 'update':
                        if ($definition instanceof EntityTranslationDefinition && $this->translationHasParent($commit, $data)) {
                            break;
                        }

                        $payload = $data->getPayload();
                        if (empty($payload)) {
                            break;
                        }
                        $payload = $this->addVersionToPayload($payload, $definition, Defaults::LIVE_VERSION);
                        $payload = $this->addTranslationToPayload($data->getEntityId(), $payload, $definition, $commit);
                        $writes[$data->getAction()][$definition->getEntityName()][] = $payload;

                        break;
                    case 'delete':
                        $id = $data->getEntityId();
                        $id = $this->addVersionToPayload($id, $definition, Defaults::LIVE_VERSION);
                        $writes['delete'][$definition->getEntityName()][] = $id;

                        break;
                }
            }
            $writes['delete']['version_commit'][] = ['id' => $commit->getId()];
        }

        return $writes;
    }

    /**
     * @param array{insert:array<string, array<int, mixed>>, update:array<string, array<int, mixed>>, delete:array<string, array<int, mixed>>} $writes
     */
    private function executeWrites(array $writes, WriteContext $liveContext): WriteResult
    {
        $operations = [];
        foreach ($writes['insert'] as $entity => $payload) {
            $operations[] = new SyncOperation('insert-' . $entity, $entity, 'upsert', $payload);
        }
        foreach ($writes['update'] as $entity => $payload) {
            $operations[] = new SyncOperation('update-' . $entity, $entity, 'upsert', $payload);
        }
        foreach ($writes['delete'] as $entity => $payload) {
            $operations[] = new SyncOperation('delete-' . $entity, $entity, 'delete', $payload);
        }

        return $this->entityWriter->sync($operations, $liveContext);
    }

    private function updateVersionData(VersionCommitCollection $commits, WriteContext $writeContext, string $versionId): void
    {
        $new = [];

        foreach ($commits as $commit) {
            foreach ($commit->getData() as $data) {
                // skip clone action, otherwise the payload would contain all data
                if ($data->getAction() === 'clone' || $data->getPayload() === null) {
                    continue;
                }
                $definition = $this->registry->getByEntityName($data->getEntityName());

                $id = $data->getEntityId();
                $id = $this->addVersionToPayload($id, $definition, Defaults::LIVE_VERSION);

                $payload = $this->addVersionToPayload($data->getPayload(), $definition, Defaults::LIVE_VERSION);

                $new[] = [
                    'entityId' => $id,
                    'payload' => Json::encode($payload),
                    'userId' => $data->getUserId(),
                    'integrationId' => $data->getIntegrationId(),
                    'entityName' => $data->getEntityName(),
                    'action' => $data->getAction(),
                    'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ];
            }
        }

        $commit = [
            'versionId' => Defaults::LIVE_VERSION,
            'data' => $new,
            'userId' => $writeContext->getContext()->getSource() instanceof AdminApiSource ? $writeContext->getContext()->getSource()->getUserId() : null,
            'isMerge' => true,
            'message' => 'merge commit ' . (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        // create new version commit for merge commit
        $this->entityWriter->insert($this->versionCommitDefinition, [$commit], $writeContext);

        // delete version
        $this->entityWriter->delete($this->versionDefinition, [['id' => $versionId]], $writeContext);
    }

    private function deleteClones(VersionCommitCollection $commits, WriteContext $versionContext, string $versionId): void
    {
        $handled = [];

        foreach ($commits as $commit) {
            foreach ($commit->getData() as $data) {
                $definition = $this->registry->getByEntityName($data->getEntityName());

                $entity = [
                    'definition' => $definition->getEntityName(),
                    'primary' => $data->getEntityId(),
                ];

                // deduplicate to prevent deletion errors
                $entityKey = md5(Json::encode($entity));
                if (isset($handled[$entityKey])) {
                    continue;
                }
                $handled[$entityKey] = $entity;

                $primary = $this->addVersionToPayload($data->getEntityId(), $definition, $versionId);

                $this->entityWriter->delete($definition, [$primary], $versionContext);
            }
        }
    }
}
