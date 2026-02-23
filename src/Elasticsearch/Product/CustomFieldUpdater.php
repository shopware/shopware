<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSetRelation\CustomFieldSetRelationDefinition;
use Shopware\Core\System\CustomField\CustomFieldDefinition;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
class CustomFieldUpdater implements EventSubscriberInterface
{
    public function __construct(
        private readonly ElasticsearchHelper $elasticsearchHelper,
        private readonly CustomFieldSetGateway $customFieldSetGateway,
        private readonly ElasticsearchCustomFieldsMappingHelper $mappingHelper
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EntityWrittenContainerEvent::class => 'indexCustomFields',
        ];
    }

    public function indexCustomFields(EntityWrittenContainerEvent $containerEvent): void
    {
        $customFieldWrittenEvent = $containerEvent->getEventByEntityName(CustomFieldDefinition::ENTITY_NAME);
        $customFieldRelationWrittenEvent = $containerEvent->getEventByEntityName(CustomFieldSetRelationDefinition::ENTITY_NAME);

        if ($customFieldWrittenEvent === null && $customFieldRelationWrittenEvent === null) {
            return;
        }

        if (!$this->elasticsearchHelper->allowIndexing()) {
            return;
        }

        if ($customFieldRelationWrittenEvent !== null) {
            $this->customFieldRelationsUpdated($customFieldRelationWrittenEvent);
        }

        if ($customFieldWrittenEvent !== null) {
            $this->customFieldsCreated($customFieldWrittenEvent);
            $this->customFieldsUpdated($containerEvent, $customFieldWrittenEvent);
        }
    }

    /**
     * @deprecated tag:v6.8.0 - Use ElasticsearchCustomFieldsMappingHelper::getTypeFromCustomFieldType instead
     *
     * @return array{type: string}
     */
    public static function getTypeFromCustomFieldType(string $type): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'Use ElasticsearchCustomFieldsMappingHelper::getTypeFromCustomFieldType instead')
        );

        return ElasticsearchCustomFieldsMappingHelper::getTypeFromCustomFieldType($type);
    }

    private function customFieldRelationsUpdated(EntityWrittenEvent $customFieldRelationWrittenEvent): void
    {
        $updatedCustomFieldSetIds = [];
        foreach ($customFieldRelationWrittenEvent->getWriteResults() as $writeResult) {
            $existence = $writeResult->getExistence();

            if ($existence && $existence->exists()) {
                continue;
            }

            if ($writeResult->getProperty('entityName') !== 'product') {
                continue;
            }

            $updatedCustomFieldSetIds[] = $writeResult->getProperty('customFieldSetId');
        }

        if ($updatedCustomFieldSetIds === []) {
            return;
        }

        $customFieldsBySet = $this->customFieldSetGateway->fetchCustomFieldsForSets($updatedCustomFieldSetIds);
        $allCustomFields = array_merge([], ...array_values($customFieldsBySet));
        $fields = ElasticsearchCustomFieldsMappingHelper::mapCustomFieldsToEsTypes(
            array_column($allCustomFields, 'type', 'name')
        );

        $this->mappingHelper->createFieldsInIndices($fields);
    }

    private function customFieldsCreated(EntityWrittenEvent $customFieldWrittenEvent): void
    {
        $results = [];

        foreach ($customFieldWrittenEvent->getWriteResults() as $writeResult) {
            $existence = $writeResult->getExistence();

            if ($existence && $existence->exists()) {
                continue;
            }

            $key = $writeResult->getPrimaryKey();
            \assert(\is_string($key));
            $results[$key] = $writeResult;
        }

        if ($results === []) {
            return;
        }

        $fieldSetIds = $this->customFieldSetGateway->fetchFieldSetIds(array_keys($results));
        $uniqueSetIds = array_values(array_unique($fieldSetIds));
        $fieldSetEntityMappings = $this->customFieldSetGateway->fetchFieldSetEntityMappings($uniqueSetIds);
        $appOwnedSetIds = $this->customFieldSetGateway->fetchAppOwnedFieldSetIds($uniqueSetIds);

        $results = array_filter(
            $results,
            static function (EntityWriteResult $writeResult, string $id) use ($fieldSetEntityMappings, $fieldSetIds, $appOwnedSetIds): bool {
                $setId = $fieldSetIds[$id] ?? null;
                if ($setId === null) {
                    return false;
                }

                if (!\in_array('product', $fieldSetEntityMappings[$setId] ?? [], true)) {
                    return false;
                }

                if (\in_array($setId, $appOwnedSetIds, true)) {
                    return true;
                }

                $payload = $writeResult->getPayload();

                return \array_key_exists('includeInSearch', $payload) && (bool) $payload['includeInSearch'];
            },
            \ARRAY_FILTER_USE_BOTH
        );

        if ($results === []) {
            return;
        }

        $nameTypeMap = [];
        foreach ($results as $writeResult) {
            $nameTypeMap[$writeResult->getProperty('name')] = $writeResult->getProperty('type');
        }

        $newCreatedFields = ElasticsearchCustomFieldsMappingHelper::mapCustomFieldsToEsTypes($nameTypeMap);

        $this->mappingHelper->createFieldsInIndices($newCreatedFields);
    }

    private function customFieldsUpdated(
        EntityWrittenContainerEvent $containerEvent,
        EntityWrittenEvent $customFieldWrittenEvent
    ): void {
        $customFieldIds = $containerEvent->getPrimaryKeysWithPropertyChange(
            CustomFieldDefinition::ENTITY_NAME,
            ['includeInSearch']
        );

        if ($customFieldIds === []) {
            return;
        }

        $updatedFieldIds = [];
        foreach ($customFieldWrittenEvent->getWriteResults() as $writeResult) {
            $key = (string) $writeResult->getPrimaryKey();
            if (!\in_array($key, $customFieldIds, true)) {
                continue;
            }

            $existence = $writeResult->getExistence();
            if (!$existence || !$existence->exists()) {
                continue;
            }

            $payload = $writeResult->getPayload();
            if (!\array_key_exists('includeInSearch', $payload) || !(bool) $payload['includeInSearch']) {
                continue;
            }

            $updatedFieldIds[$key] = true;
        }

        if ($updatedFieldIds === []) {
            return;
        }

        $fieldSetIds = $this->customFieldSetGateway->fetchFieldSetIds(array_keys($updatedFieldIds));

        if ($fieldSetIds === []) {
            return;
        }

        $setIds = array_values(array_unique($fieldSetIds));
        $fieldSetEntityMappings = $this->customFieldSetGateway->fetchFieldSetEntityMappings($setIds);

        $customFieldsBySet = $this->customFieldSetGateway->fetchCustomFieldsForSets($setIds);

        $fieldsToAdd = [];
        foreach ($customFieldsBySet as $setCustomFields) {
            foreach ($setCustomFields as $customField) {
                $customFieldId = $customField['id'];

                if (isset($updatedFieldIds[$customFieldId])) {
                    $setId = $fieldSetIds[$customFieldId];
                    if (\in_array('product', $fieldSetEntityMappings[$setId] ?? [], true)) {
                        $fieldsToAdd[$customField['name']] = ElasticsearchCustomFieldsMappingHelper::getTypeFromCustomFieldType($customField['type']);
                    }
                }
            }
        }

        $this->mappingHelper->createFieldsInIndices($fieldsToAdd);
    }
}
