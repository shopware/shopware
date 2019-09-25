<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Context\AdminApiSource;
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
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DeleteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Version\Aggregate\VersionCommit\VersionCommitCollection;
use Shopware\Core\Framework\Version\Aggregate\VersionCommit\VersionCommitDefinition;
use Shopware\Core\Framework\Version\Aggregate\VersionCommitData\VersionCommitDataDefinition;
use Shopware\Core\Framework\Version\Aggregate\VersionCommitData\VersionCommitDataEntity;
use Shopware\Core\Framework\Version\VersionDefinition;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Serializer\SerializerInterface;

class VersionManager
{
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
        VersionDefinition $versionDefinition
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
    }

    public function upsert(EntityDefinition $definition, array $rawData, WriteContext $writeContext): array
    {
        $writtenEvent = $this->entityWriter->upsert($definition, $rawData, $writeContext);

        if ($definition->isVersionAware()) {
            $this->writeAuditLog($writtenEvent, $writeContext, __FUNCTION__);
        }

        return $writtenEvent;
    }

    public function insert(EntityDefinition $definition, array $rawData, WriteContext $writeContext): array
    {
        $writtenEvent = $this->entityWriter->insert($definition, $rawData, $writeContext);

        $this->writeAuditLog($writtenEvent, $writeContext, __FUNCTION__);

        return $writtenEvent;
    }

    public function update(EntityDefinition $definition, array $rawData, WriteContext $writeContext): array
    {
        $writtenEvent = $this->entityWriter->update($definition, $rawData, $writeContext);

        $this->writeAuditLog($writtenEvent, $writeContext, __FUNCTION__);

        return $writtenEvent;
    }

    public function delete(EntityDefinition $definition, array $ids, WriteContext $writeContext): DeleteResult
    {
        $deleteEvent = $this->entityWriter->delete($definition, $ids, $writeContext);

        $this->writeAuditLog($deleteEvent->getDeleted(), $writeContext, __FUNCTION__);

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

        $this->entityWriter->upsert($this->versionDefinition, [$versionData], $context);

        $affected = $this->clone($definition, $primaryKey['id'], $primaryKey['id'], $versionId, $context, false);

        $versionContext = $context->createWithVersionId($versionId);

        $event = EntityWrittenContainerEvent::createWithWrittenEvents($affected, $versionContext->getContext(), []);
        $this->eventDispatcher->dispatch($event);

        $this->writeAuditLog($affected, $context, 'clone', $versionId);

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
        $commits = $this->entityReader->read($this->versionCommitDefinition, $readCriteria, $writeContext->getContext());

        $allChanges = [];
        $entities = [];

        $versionContext = $writeContext->createWithVersionId($versionId);
        $liveContext = $writeContext->createWithVersionId(Defaults::LIVE_VERSION);

        $writtenEvents = [];
        $deletedEvents = [];

        /** @var VersionCommitCollection $commits */
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

                switch ($data->getAction()) {
                    case 'insert':
                    case 'update':
                    case 'upsert':
                        $payload = $this->addVersionToPayload($data->getPayload(), $dataDefinition, Defaults::LIVE_VERSION);
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

        /** @var DeleteResult[] $deletedEvents */
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
        bool $cloneChildren = true
    ): array {
        $criteria = new Criteria([$id]);
        $this->addCloneAssociations($definition, $criteria, $cloneChildren);

        $detail = $this->entityReader->read($definition, $criteria, $context->getContext())->first();

        if ($detail === null) {
            throw new \RuntimeException(sprintf('Cannot create new version. %s by id (%s) not found.', $definition->getEntityName(), $id));
        }

        $data = json_decode($this->serializer->serialize($detail, 'json'), true);

        $keepIds = $newId === $id;

        $data = $this->filterPropertiesForClone($definition, $data, $keepIds, $id, $definition, $context->getContext());
        $data['id'] = $newId;

        $versionContext = $context->createWithVersionId($versionId);
        $result = null;
        $versionContext->scope(Context::SYSTEM_SCOPE, function (WriteContext $context) use ($definition, $data, &$result): void {
            $result = $this->entityWriter->insert($definition, [$data], $context);
        });

        return $result;
    }

    private function filterPropertiesForClone(EntityDefinition $definition, array $data, bool $keepIds, string $cloneId, EntityDefinition $cloneDefinition, Context $context): array
    {
        $extensions = [];
        $payload = [];

        $fields = $definition->getFields();

        /** @var Field $field */
        foreach ($fields as $field) {
            /** @var WriteProtected|null $writeProtection */
            $writeProtection = $field->getFlag(WriteProtected::class);
            if ($writeProtection && !$writeProtection->isAllowed(Context::SYSTEM_SCOPE)) {
                continue;
            }

            //set data and payload cursor to root or extensions to simplify following if conditions
            $dataCursor = &$data;

            $payloadCursor = &$payload;

            if ($field instanceof VersionField || $field instanceof ReferenceVersionField) {
                continue;
            }

            if ($field->is(Extension::class)) {
                $dataCursor = $data['extensions'];
                $payloadCursor = &$extensions;
            }

            if (!array_key_exists($field->getPropertyName(), $dataCursor)) {
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

            if (!$field->is(CascadeDelete::class)) {
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

    private function writeAuditLog(array $writtenEvents, WriteContext $writeContext, string $action, ?string $versionId = null): void
    {
        $versionId = $versionId ?? $writeContext->getContext()->getVersionId();
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
            if (count($items) === 0) {
                continue;
            }

            //@todo@jp fix data format
            /** @var EntityDefinition $definition */
            $definition = $this->registry->getByEntityName($items[0]->getEntityName());
            $entityName = $definition->getEntityName();

            if (!$definition->isVersionAware()) {
                continue;
            }

            if (strpos('version', $entityName) === 0) {
                continue;
            }

            foreach ($items as $item) {
                $payload = $item->getPayload();

                $primary = $item->getPrimaryKey();
                if (!\is_array($primary)) {
                    $primary = ['id' => $item->getPrimaryKey()];
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
                        'action' => $action,
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

        $this->entityWriteGateway->execute($commands, $writeContext);
    }

    private function addVersionToPayload(array $payload, EntityDefinition $definition, string $versionId): array
    {
        $fields = $definition->getFields()->filter(function (Field $field) {
            return $field instanceof VersionField || $field instanceof ReferenceVersionField;
        });

        /** @var FieldCollection $fields */
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
            if (array_key_exists($pkField->getPropertyName(), $nestedItem)) {
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
        $cascades = $definition->getFields()->filterByFlag(CascadeDelete::class);

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
                    continue;
                }

                ++$childCounter;
                $this->addCloneAssociations($reference, $nested, $cloneChildren, $childCounter);

                continue;
            }

            $this->addCloneAssociations($reference, $nested, $cloneChildren);
        }
    }
}
