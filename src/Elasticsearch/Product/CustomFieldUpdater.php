<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use OpenSearch\Client;
use OpenSearch\Common\Exceptions\BadRequest400Exception;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSetRelation\CustomFieldSetRelationDefinition;
use Shopware\Core\System\CustomField\CustomFieldDefinition;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Framework\ElasticsearchOutdatedIndexDetector;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('core')]
class CustomFieldUpdater implements EventSubscriberInterface
{
    public function __construct(
        private readonly ElasticsearchOutdatedIndexDetector $indexDetector,
        private readonly Client $client,
        private readonly ElasticsearchHelper $elasticsearchHelper,
        private readonly CustomFieldSetGateway $customFieldSetGateway
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
        }
    }

    /**
     * @return array{type: string}
     */
    public static function getTypeFromCustomFieldType(string $type): array
    {
        return match ($type) {
            CustomFieldTypes::INT => [
                'type' => 'long',
            ],
            CustomFieldTypes::FLOAT => [
                'type' => 'double',
            ],
            CustomFieldTypes::BOOL => [
                'type' => 'boolean',
            ],
            CustomFieldTypes::DATETIME => [
                'type' => 'date',
                'format' => 'yyyy-MM-dd HH:mm:ss.000||strict_date_optional_time||epoch_millis',
                'ignore_malformed' => true,
            ],
            CustomFieldTypes::PRICE, CustomFieldTypes::JSON => [
                'type' => 'object',
                'dynamic' => true,
            ],
            default => AbstractElasticsearchDefinition::KEYWORD_FIELD + AbstractElasticsearchDefinition::SEARCH_FIELD,
        };
    }

    /**
     * A map of field names to their ES types, eg:
     * ['my_field' => ['type' => 'long']]
     *
     * @param array<string, array<mixed>> $newCreatedFields
     */
    private function createFieldsInIndices(array $newCreatedFields): void
    {
        if (\count($newCreatedFields) === 0) {
            return;
        }

        $indices = $this->indexDetector->getAllUsedIndices();

        foreach ($indices as $indexName) {
            $body = [
                'properties' => [
                    'customFields' => [
                        'properties' => [],
                    ],
                ],
            ];

            foreach ($this->customFieldSetGateway->fetchLanguageIds() as $languageId) {
                $body['properties']['customFields']['properties'][$languageId] = [
                    'type' => 'object',
                    'dynamic' => true,
                    'properties' => $newCreatedFields,
                ];
            }

            // For some reason, we need to include the includes to prevent merge conflicts.
            // This error can happen for example after updating from version <6.4.
            $current = $this->client->indices()->get(['index' => $indexName]);
            $includes = $current[$indexName]['mappings']['_source']['includes'] ?? [];
            if ($includes !== []) {
                $body['_source'] = [
                    'includes' => $includes,
                ];
            }

            try {
                $this->client->indices()->putMapping([
                    'index' => $indexName,
                    'body' => $body,
                ]);
            } catch (BadRequest400Exception $exception) {
                if (str_contains($exception->getMessage(), 'cannot be changed from type')) {
                    throw ElasticsearchProductException::cannotChangeCustomFieldType($exception);
                }
            }
        }
    }

    private function customFieldRelationsUpdated(EntityWrittenEvent $customFieldRelationWrittenEvent): void
    {
        $updatedCustomFieldSetIds = [];
        foreach ($customFieldRelationWrittenEvent->getWriteResults() as $writeResult) {
            $existence = $writeResult->getExistence();

            if ($existence && $existence->exists()) {
                continue;
            }

            // we only want to index custom fields relating to products
            if ($writeResult->getProperty('entityName') !== 'product') {
                continue;
            }

            $updatedCustomFieldSetIds[] = $writeResult->getProperty('customFieldSetId');
        }

        $fields = $this->mapCustomFieldsToEsTypes(
            array_merge([], ...array_values($this->customFieldSetGateway->fetchCustomFieldsForSets($updatedCustomFieldSetIds)))
        );

        $this->createFieldsInIndices($fields);
    }

    /**
     * @param array<array{name: string, type: string}> $customFields
     *
     * @return array<string, array{type: string}>
     */
    private function mapCustomFieldsToEsTypes(array $customFields): array
    {
        $esTypes = [];
        foreach ($customFields as $customField) {
            $esType = self::getTypeFromCustomFieldType($customField['type']);
            $esTypes[$customField['name']] = $esType;
        }

        return $esTypes;
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

        $fieldSetIds = $this->customFieldSetGateway->fetchFieldSetIds(array_keys($results));
        $fieldSetEntityMappings = $this->customFieldSetGateway->fetchFieldSetEntityMappings(array_values($fieldSetIds));

        // we only want to index custom fields relating to products
        $results = array_filter(
            $results,
            static fn (EntityWriteResult $writeResult, string $id) => \in_array('product', $fieldSetEntityMappings[$fieldSetIds[$id]], true),
            \ARRAY_FILTER_USE_BOTH
        );

        $newCreatedFields = $this->mapCustomFieldsToEsTypes(
            array_map(static fn (EntityWriteResult $writeResult) => [
                'name' => $writeResult->getProperty('name'),
                'type' => $writeResult->getProperty('type'),
            ], $results)
        );

        $this->createFieldsInIndices($newCreatedFields);
    }
}
