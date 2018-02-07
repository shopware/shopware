<?php

namespace Shopware\Version;

use Ramsey\Uuid\Uuid;
use Shopware\Api\Entity\DefinitionRegistry;
use Shopware\Api\Entity\Entity;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\Field\AssociationInterface;
use Shopware\Api\Entity\Field\Field;
use Shopware\Api\Entity\Field\SubresourceField;
use Shopware\Api\Entity\Field\SubVersionField;
use Shopware\Api\Entity\Field\VersionField;
use Shopware\Api\Entity\Read\EntityReaderInterface;
use Shopware\Api\Entity\RepositoryInterface;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\EntitySearcherInterface;
use Shopware\Api\Entity\Search\Query\TermQuery;
use Shopware\Api\Entity\Search\Sorting\FieldSorting;
use Shopware\Api\Entity\Write\EntityWriterInterface;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\GenericWrittenEvent;
use Shopware\Api\Entity\Write\WriteContext;
use Shopware\Api\Version\Collection\VersionCommitBasicCollection;
use Shopware\Api\Version\Definition\VersionCommitDefinition;
use Shopware\Api\Version\Definition\VersionDefinition;
use Shopware\Api\Version\Repository\VersionChangeRepository;
use Shopware\Api\Version\Repository\VersionCommitDataRepository;
use Shopware\Api\Version\Repository\VersionCommitRepository;
use Shopware\Api\Version\Repository\VersionRepository;
use Shopware\Api\Version\Struct\VersionCommitBasicStruct;
use Shopware\Api\Version\Struct\VersionCommitDataBasicStruct;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Defaults;
use Shopware\Framework\Struct\Collection;
use Symfony\Component\DependencyInjection\Container;

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
     * VersionManager constructor.
     * @param EntityWriterInterface $entityWriter
     * @param EntityReaderInterface $entityReader
     * @param EntitySearcherInterface $entitySearcher
     * @param DefinitionRegistry $entityDefinitionRegistry
     */
    public function __construct(
        EntityWriterInterface $entityWriter,
        EntityReaderInterface $entityReader,
        EntitySearcherInterface $entitySearcher,
        DefinitionRegistry $entityDefinitionRegistry
    )
    {
        $this->entityWriter = $entityWriter;
        $this->entityReader = $entityReader;
        $this->entitySearcher = $entitySearcher;
        $this->entityDefinitionRegistry = $entityDefinitionRegistry;
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

    public function delete(string $definition, array $ids, WriteContext $writeContext)
    {
        $writtenEvent = $this->entityWriter->delete($definition, $ids, $writeContext);

        $this->writeAuditLog($writtenEvent, $writeContext, __FUNCTION__);

        return $writtenEvent;
    }

    public function createVersion(string $definition, array $primaryKey, WriteContext $context, ?string $name = null): string
    {
        if (!array_key_exists('versionId', $primaryKey)) {
            $primaryKey['versionId'] = Defaults::LIVE_VERSION;
        }

        $versionData = [
            'id' => Uuid::uuid4()->toString(),
        ];

        if ($name) {
            $versionData['name'] = $name;
        }

        $this->entityWriter->insert(VersionDefinition::class, [$versionData], $context);

        /** @var EntityDefinition|string $definition */
        $identifiers = $this->clone($definition, $primaryKey, $versionData['id'], $context);

        $this->writeAuditLog($identifiers, $context, 'clone');

        return $versionData['id'];
    }

    public function merge(string $versionId, WriteContext $context)
    {
        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('version_commit.versionId', $versionId));
        $criteria->addSorting(new FieldSorting('version_commit.ai'));

        $translationContext = $context->getTranslationContext();

        $commitIds = $this->entitySearcher->search(VersionCommitDefinition::class, $criteria, $translationContext);
        $commits = $this->entityReader->readBasic(VersionCommitDefinition::class, $commitIds->getIds(), $translationContext);

        $allChanges = [];
        $entities = [];

        /** @var VersionCommitBasicCollection $commits */
        foreach ($commits as $commit) {
            foreach ($commit->getData() as $data) {
                $dataDefinition = $this->entityDefinitionRegistry->get($data->getEntityName());

                if ($data->getAction() !== 'clone') {
                    $allChanges[] = $data;
                }

                $entities[] = [
                    'definition' => $dataDefinition,
                    'primary' => $data->getEntityId()
                ];

                switch ($data->getAction()) {
                    case 'insert':
                    case 'update':
                        $payload = json_decode($data->getPayload(), true);
                        $payload['versionId'] = Defaults::LIVE_VERSION;
                        $this->entityWriter->upsert($dataDefinition, [$payload], $context);
                    break;

                    case 'delete':
                        $id = $data->getEntityId();
                        $id['versionId'] = Defaults::LIVE_VERSION;

                        $this->entityWriter->delete($dataDefinition, [$id], $context);
                        break;
                }

            }

            $this->entityWriter->delete(VersionCommitDefinition::class, [['id' => $commit->getId()]], $context);
        }

        $newData = array_map(function(VersionCommitDataBasicStruct $data) {
            $id = $data->getEntityId();
            $id['versionId'] = Defaults::LIVE_VERSION;

            $payload = json_decode($data->getPayload(), true);
            $payload['versionId'] = Defaults::LIVE_VERSION;

            return [
                'entityId' => json_encode($id),
                'payload' => json_encode($payload),
                'entityName' => $data->getEntityName(),
                'action' => $data->getAction(),
                'createdAt' => (new \DateTime())->format(\DateTime::ATOM)
            ];
        }, $allChanges);

        $commit = [
            'versionId' => Defaults::LIVE_VERSION,
            'data' => $newData,
            'message' => 'merge commit ' . (new \DateTime())->format(\DateTime::ATOM)
        ];

        $this->entityWriter->insert(VersionCommitDefinition::class, [$commit], $context);
        $this->entityWriter->delete(VersionDefinition::class, [['id' => $versionId]], $context);

        foreach ($entities as $entity) {
            $primary = $entity['primary'];
            $definition = $entity['definition'];

            if ($primary['versionId'] !== $versionId) {
                continue;
            }
            $this->entityWriter->delete($definition, [$primary], $context);
        }

        return;



        $payload = $this->removeVersion($definition, $payload);

        /** @var RepositoryInterface $repository */
        $repository = $this->container->get($definition::getRepositoryClass());
        $repository->update([$payload], $context);

        $primary = ['id' => $entityId, 'versionId' => $versionId];
        $repository->delete([$primary], $context);

        $commitIds = $commits->map(function (VersionCommitBasicStruct $change) {
            return ['id' => $change->getId()];
        });

//        $this->versionChangeRepository->delete(array_values($changeIds), $context);
//        $this->versionRepository->delete([['id' => $versionId]], $context);
    }

    private function clone(string $definition, array $primaryKey, string $versionId, WriteContext $context): array
    {
        /** @var Entity $detail */
        /** @var string|EntityDefinition $definition */
        $detail = $this->entityReader->readDetail($definition, [$primaryKey['id']], $context->getTranslationContext())->first();

        if ($detail === null) {
            throw new \Exception(sprintf('Cannot create new version. %s by id (%s) not found.', $definition::getEntityName(), print_r($primaryKey, true)));
        }

        $fields = $definition::getFields()->filter(function (Field $field) {
            return !($field instanceof AssociationInterface) || $field->is(CascadeDelete::class);
        });

        $detailArray = $detail->jsonSerialize();

        $payload = [];
        foreach ($detailArray as $key => $value) {
            if (!in_array($key, $fields->getKeys()) || !$value) {
                continue;
            }

            $payload[$key] = $this->convertValue($value);
        }

        $payload = array_filter($payload);
        $payload = $this->removeVersion($definition, $payload);
        $payload['versionId'] = $versionId;
        $payload['id'] = $detail->getId();

        return $this->entityWriter->insert($definition, [$payload], $context);
    }

    /**
     * @param string|EntityDefinition $definition
     * @param array $payload
     * @return array
     */
    private function removeVersion(string $definition, array $payload): array
    {
        $fields = $definition::getFields()->filter(function (Field $field) {
            return $field instanceof VersionField || $field instanceof SubVersionField;
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

        return $value;
    }


    /**
     * @param string|EntityDefinition $definition
     * @param array $writtenEvents
     * @param WriteContext $writeContext
     * @param string $action
     */
    private function writeAuditLog(array $writtenEvents, WriteContext $writeContext, string $action)
    {
        $userId = null;//$this->getUserId($writeContext->getTranslationContext());

        $commits = [];

        /**
         * @var string|EntityDefinition $definition
         * @var array $item
         */
        foreach ($writtenEvents as $definition => $items) {

            if (strpos('version', $definition::getEntityName()) === 0) {
                continue;
            }

            foreach ($items as $item) {
                $payload = $item['payload'];

                $versionId = $payload['versionId'];

                if (!array_key_exists($versionId, $commits)) {
                    $commit = array_filter([
                        'versionId' => $versionId,
                        'userId' => $userId,
                        'data' => []
                    ]);

                } else {
                    $commit = $commits[$versionId];
                }

                // writing to live version, no logging enabled
                $commit['data'][] = [
                    'entityName' => $definition::getEntityName(),
                    'entityId' => json_encode($item['primaryKey']),
                    'payload' => json_encode($payload),
                    'action' => $action,
                    'createdAt' => new \DateTime(),
                ];

                $commits[$versionId] = $commit;
            }
        }

        $commits = array_values($commits);

        if (empty($commits)) {
            return;
        }

        $this->entityWriter->insert(VersionCommitDefinition::class, $commits, $writeContext);
    }

    private function getUserId(TranslationContext $context): ?string
    {
        $token = $this->tokenStorage->getToken();
        if (!$token) {
            return null;
        }

        /** @var UserInterface $user */
        $user = $token->getUser();

        $name = $user->getUsername();
        if (array_key_exists($name, $this->mapping)) {
            return $this->mapping[$name];
        }

        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new TermQuery(UserDefinition::getEntityName() . '.username', $name));

        $users = $this->searcher->search(UserDefinition::class, $criteria, $context);
        $ids = $users->getIds();

        $id = array_shift($ids);

        if (!$id) {
            return $this->mapping[$name] = null;
        }

        return $this->mapping[$name] = $id;
    }
}