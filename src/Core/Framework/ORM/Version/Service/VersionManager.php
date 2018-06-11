<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Version\Service;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\DefinitionRegistry;
use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Field\AssociationInterface;
use Shopware\Core\Framework\ORM\Field\Field;
use Shopware\Core\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Core\Framework\ORM\Field\SubresourceField;
use Shopware\Core\Framework\ORM\Field\VersionField;
use Shopware\Core\Framework\ORM\Read\EntityReaderInterface;
use Shopware\Core\Framework\ORM\Read\ReadCriteria;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\EntitySearcherInterface;
use Shopware\Core\Framework\ORM\Search\Query\TermQuery;
use Shopware\Core\Framework\ORM\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\ORM\Version\Collection\VersionCommitBasicCollection;
use Shopware\Core\Framework\ORM\Version\Definition\VersionCommitDataDefinition;
use Shopware\Core\Framework\ORM\Version\Definition\VersionCommitDefinition;
use Shopware\Core\Framework\ORM\Version\Definition\VersionDefinition;
use Shopware\Core\Framework\ORM\Version\Struct\VersionCommitDataBasicStruct;
use Shopware\Core\Framework\ORM\Write\Command\InsertCommand;
use Shopware\Core\Framework\ORM\Write\EntityExistence;
use Shopware\Core\Framework\ORM\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\ORM\Write\EntityWriterInterface;
use Shopware\Core\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\ORM\Write\WriteContext;
use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\System\User\UserDefinition;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

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
     * @var DefinitionRegistry
     */
    private $entityDefinitionRegistry;

    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    /**
     * @var array
     */
    private $mapping = [];

    /**
     * @var EntityWriteGatewayInterface
     */
    private $entityWriteGateway;

    public function __construct(
        EntityWriterInterface $entityWriter,
        EntityReaderInterface $entityReader,
        EntitySearcherInterface $entitySearcher,
        DefinitionRegistry $entityDefinitionRegistry,
        TokenStorageInterface $tokenStorage,
        EntityWriteGatewayInterface $entityWriteGateway
    ) {
        $this->entityWriter = $entityWriter;
        $this->entityReader = $entityReader;
        $this->entitySearcher = $entitySearcher;
        $this->entityDefinitionRegistry = $entityDefinitionRegistry;
        $this->tokenStorage = $tokenStorage;
        $this->entityWriteGateway = $entityWriteGateway;
    }

    public function upsert(string $definition, array $rawData, WriteContext $writeContext): array
    {
        $writtenEvent = $this->entityWriter->upsert($definition, $rawData, $writeContext);

        $this->writeAuditLog($writtenEvent, $writeContext, __FUNCTION__);

        return $writtenEvent;
    }

    public function insert(string $definition, array $rawData, WriteContext $writeContext): array
    {
        $writtenEvent = $this->entityWriter->insert($definition, $rawData, $writeContext);

        $this->writeAuditLog($writtenEvent, $writeContext, __FUNCTION__);

        return $writtenEvent;
    }

    public function update(string $definition, array $rawData, WriteContext $writeContext): array
    {
        $writtenEvent = $this->entityWriter->update($definition, $rawData, $writeContext);

        $this->writeAuditLog($writtenEvent, $writeContext, __FUNCTION__);

        return $writtenEvent;
    }

    public function delete(string $definition, array $ids, WriteContext $writeContext): array
    {
        $writtenEvent = $this->entityWriter->delete($definition, $ids, $writeContext);

        $this->writeAuditLog($writtenEvent, $writeContext, __FUNCTION__);

        return $writtenEvent;
    }

    public function createVersion(string $definition, string $id, WriteContext $context, ?string $name = null, ?string $versionId = null): string
    {
        $primaryKey = [
            'id' => $id,
            'versionId' => Defaults::LIVE_VERSION,
        ];

        $versionId = $versionId ?? Uuid::uuid4()->getHex();
        $versionData = ['id' => $versionId];

        if ($name) {
            $versionData['name'] = $name;
        } else {
            /* @var EntityDefinition|string $definition */
            $versionData['name'] = $definition::getEntityName() . (new \DateTime())->format(\DateTime::ATOM);
        }

        $this->entityWriter->upsert(VersionDefinition::class, [$versionData], $context);

        /** @var EntityDefinition|string $definition */
        $identifiers = $this->clone($definition, $primaryKey, $versionId, $context);

        $this->writeAuditLog($identifiers, $context, 'clone', $versionId);

        return $versionData['id'];
    }

    public function merge(string $versionId, WriteContext $writeContext): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('version_commit.versionId', $versionId));
        $criteria->addSorting(new FieldSorting('version_commit.autoIncrement'));

        $commitIds = $this->entitySearcher->search(VersionCommitDefinition::class, $criteria, $writeContext->getContext());
        $commits = $this->entityReader->read(VersionCommitDefinition::class, new ReadCriteria($commitIds->getIds()), $writeContext->getContext());

        $allChanges = [];
        $entities = [];

        $versionContext = $writeContext->createWithVersionId($versionId);
        $liveContext = $writeContext->createWithVersionId(Defaults::LIVE_VERSION);

        /** @var VersionCommitBasicCollection $commits */
        foreach ($commits as $commit) {
            foreach ($commit->getData() as $data) {
                $dataDefinition = $this->entityDefinitionRegistry->get($data->getEntityName());

                if ($data->getAction() !== 'clone') {
                    $allChanges[] = $data;
                }

                $entities[] = [
                    'definition' => $dataDefinition,
                    'primary' => $data->getEntityId(),
                ];

                switch ($data->getAction()) {
                    case 'insert':
                    case 'update':
                    case 'upsert':
                        $payload = $this->addVersionToPayload($data->getPayload(), $dataDefinition, Defaults::LIVE_VERSION);
                        $this->entityWriter->upsert($dataDefinition, [$payload], $liveContext);
                        break;

                    case 'delete':
                        $id = $data->getEntityId();
                        $id = $this->addVersionToPayload($id, $dataDefinition, Defaults::LIVE_VERSION);
                        $this->entityWriter->delete($dataDefinition, [$id], $liveContext);
                        break;
                }
            }

            $this->entityWriter->delete(VersionCommitDefinition::class, [['id' => $commit->getId()]], $liveContext);
        }

        $newData = array_map(function (VersionCommitDataBasicStruct $data) {
            $definition = $this->entityDefinitionRegistry->get($data->getEntityName());

            $id = $data->getEntityId();
            $id = $this->addVersionToPayload($id, $definition, Defaults::LIVE_VERSION);

            $payload = $this->addVersionToPayload($data->getPayload(), $definition, Defaults::LIVE_VERSION);

            return [
                'entityId' => $id,
                'payload' => json_encode($payload),
                'userId' => $data->getUserId(),
                'entityName' => $data->getEntityName(),
                'action' => $data->getAction(),
                'createdAt' => (new \DateTime())->format(\DateTime::ATOM),
            ];
        }, $allChanges);

        $commit = [
            'versionId' => Defaults::LIVE_VERSION,
            'data' => $newData,
            'userId' => $this->getUserId($writeContext->getContext()),
            'isMerge' => true,
            'message' => 'merge commit ' . (new \DateTime())->format(\DateTime::ATOM),
        ];

        $this->entityWriter->insert(VersionCommitDefinition::class, [$commit], $writeContext);
        $this->entityWriter->delete(VersionDefinition::class, [['id' => $versionId]], $writeContext);

        foreach ($entities as $entity) {
            $primary = $entity['primary'];
            $definition = $entity['definition'];
            $primary = $this->addVersionToPayload($primary, $definition, $versionId);

            $this->entityWriter->delete($definition, [$primary], $versionContext);
        }
    }

    private function clone(string $definition, array $primaryKey, string $versionId, WriteContext $context): array
    {
        /** @var Entity $detail */
        /** @var string|EntityDefinition $definition */
        $detail = $this->entityReader->readRaw($definition, new ReadCriteria([$primaryKey['id']]), $context->getContext())->first();

        if ($detail === null) {
            throw new \Exception(sprintf('Cannot create new version. %s by id (%s) not found.', $definition::getEntityName(), print_r($primaryKey, true)));
        }

        $fields = $definition::getFields()->filter(function (Field $field) {
            return !($field instanceof AssociationInterface) || $field->is(CascadeDelete::class);
        });

        $detailArray = $detail->jsonSerialize();

        $payload = [];

        foreach ($detailArray as $key => $value) {
            if (!in_array($key, $fields->getKeys(), true) || !$value) {
                continue;
            }

            $payload[$key] = $this->convertValue($value);
        }

        $payload = array_filter($payload);
        $payload = $this->removeVersion($definition, $payload);
        $payload['id'] = $detail->getId();

        //do not versioning child elements, child elements can have their own version
        if (array_key_exists('children', $payload)) {
            unset($payload['children']);
        }

        $newContext = $context->createWithVersionId($versionId);

        return $this->entityWriter->insert($definition, [$payload], $newContext);
    }

    /**
     * @param string|EntityDefinition $definition
     * @param array                   $payload
     *
     * @return array
     */
    private function removeVersion(string $definition, array $payload): array
    {
        $fields = $definition::getFields()->filter(function (Field $field) {
            return $field instanceof VersionField;
        });

        /** @var Field $field */
        foreach ($fields as $field) {
            $key = $field->getPropertyName();

            if (!array_key_exists($key, $payload)) {
                continue;
            }

            unset($payload[$key]);
        }

        $associationFields = $definition::getFields()->filterByFlag(CascadeDelete::class);

        /** @var Field|AssociationInterface $field */
        foreach ($associationFields as $field) {
            $key = $field->getPropertyName();

            if (!array_key_exists($key, $payload)) {
                continue;
            }

            if ($field instanceof SubresourceField) {
                foreach ($payload[$key] as $index => $associationItem) {
                    $payload[$key][$index] = $this->removeVersion($field->getReferenceClass(), $associationItem);
                }
            } else {
                $payload[$key] = $this->removeVersion($field->getReferenceClass(), $payload[$key]);
            }
        }

        return $payload;
    }

    private function convertValue($value)
    {
        if ($value instanceof \DateTime) {
            return $value->format(\DateTime::ATOM);
        }

        if ($value instanceof Collection) {
            $elements = [];
            if ($value->count() === 0) {
                return $elements;
            }

            /** @var Entity $element */
            foreach ($value->getElements() as $element) {
                $entity = $element->jsonSerialize();

                foreach ($entity as &$data) {
                    $data = $this->convertValue($data);
                }

                $elements[] = array_filter($entity);
            }

            return $elements;
        }

        if ($value instanceof Struct) {
            $entity = $value->jsonSerialize();

            foreach ($entity as &$data) {
                $data = $this->convertValue($data);
            }

            return array_filter($entity);
        }

        return $value;
    }

    private function writeAuditLog(array $writtenEvents, WriteContext $writeContext, string $action, ?string $versionId = null): void
    {
        $userId = $this->getUserId($writeContext->getContext());

        $userId = $userId ? Uuid::fromStringToBytes($userId) : null;

        $versionId = $versionId ?? $writeContext->getContext()->getVersionId();

        $commitId = Uuid::uuid4();

        $date = (new \DateTime())->format('Y-m-d H:i:s');

        $tenantId = Uuid::fromStringToBytes($writeContext->getContext()->getTenantId());

        $insert = new InsertCommand(
            VersionCommitDefinition::class,
            [
                'id' => $commitId->getBytes(),
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'version_id' => Uuid::fromStringToBytes($versionId),
                'version_tenant_id' => $tenantId,
                'created_at' => $date,
            ],
            ['id' => $commitId->getBytes()],
            new EntityExistence(
                VersionCommitDefinition::class,
                ['id' => $commitId->getBytes(), 'tenant_id' => $tenantId],
                false,
                false,
                false,
                []
            )
        );

        $commands = [$insert];

        /*
         * @var string|EntityDefinition
         * @var array                   $item
         */
        foreach ($writtenEvents as $definition => $items) {
            if (strpos('version', $definition::getEntityName()) === 0) {
                continue;
            }

            foreach ($items as $item) {
                $payload = $item['payload'];

                $primary = $item['primaryKey'];
                if (!is_array($primary)) {
                    $primary = ['id' => $item['primaryKey']];
                }
                $primary['versionId'] = $versionId;

                $id = Uuid::uuid4()->getBytes();

                $commands[] = new InsertCommand(
                    VersionCommitDataDefinition::class,
                    [
                        'id' => $id,
                        'tenant_id' => $tenantId,
                        'version_commit_id' => $commitId->getBytes(),
                        'version_commit_tenant_id' => $tenantId,
                        'entity_name' => $definition::getEntityName(),
                        'entity_id' => json_encode($primary),
                        'payload' => json_encode($payload),
                        'user_id' => $userId,
                        'action' => $action,
                        'created_at' => $date,
                    ],
                    ['id' => $id],
                    new EntityExistence(
                        VersionCommitDataDefinition::class,
                        ['id' => $id, 'tenant_id' => $tenantId],
                        false,
                        false,
                        false,
                        []
                    )
                );
            }
        }

        if (count($commands) <= 1) {
            return;
        }

        $this->entityWriteGateway->execute($commands);
    }

    private function getUserId(Context $context): ?string
    {
        $token = $this->tokenStorage->getToken();
        if (!$token) {
            return null;
        }

        /** @var UserInterface $user */
        $user = $token->getUser();

        if (!$user instanceof UserInterface) {
            return null;
        }

        $name = $user->getUsername();
        if (array_key_exists($name, $this->mapping)) {
            return $this->mapping[$name];
        }

        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new TermQuery(UserDefinition::getEntityName() . '.username', $name));

        $users = $this->entitySearcher->search(UserDefinition::class, $criteria, $context);
        $ids = $users->getIds();

        $id = array_shift($ids);

        if (!$id) {
            return $this->mapping[$name] = null;
        }

        return $this->mapping[$name] = $id;
    }

    private function addVersionToPayload(array $payload, string $definition, string $versionId): array
    {
        /** @var string|EntityDefinition $definition */
        $fields = $definition::getFields()->filter(function (Field $field) {
            return $field instanceof VersionField || $field instanceof ReferenceVersionField;
        });

        /** @var Field $field */
        foreach ($fields as $field) {
            $payload[$field->getPropertyName()] = $versionId;
        }

        return $payload;
    }
}
