<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingDefinition;
use Shopware\Core\Content\ProductStream\Aggregate\ProductStreamFilter\ProductStreamFilterDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('inventory')]
class ProductSortingStreamUpdater implements EventSubscriberInterface
{
    public function __construct(
        private readonly ElasticsearchHelper $elasticsearchHelper,
        private readonly ElasticsearchCustomFieldsMappingHelper $mappingHelper,
        private readonly Connection $connection
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EntityWrittenContainerEvent::class => 'onEntityWritten',
        ];
    }

    public function onEntityWritten(EntityWrittenContainerEvent $containerEvent): void
    {
        $productSortingEvent = $containerEvent->getEventByEntityName(ProductSortingDefinition::ENTITY_NAME);
        $productStreamFilterEvent = $containerEvent->getEventByEntityName(ProductStreamFilterDefinition::ENTITY_NAME);

        if ($productSortingEvent === null && $productStreamFilterEvent === null) {
            return;
        }

        if (!$this->elasticsearchHelper->allowIndexing()) {
            return;
        }

        if ($productSortingEvent !== null) {
            $this->productSortingWritten($productSortingEvent);
        }

        if ($productStreamFilterEvent !== null) {
            $this->productStreamFilterWritten($productStreamFilterEvent);
        }
    }

    private function productSortingWritten(EntityWrittenEvent $event): void
    {
        $customFieldNames = [];

        $productSortingIds = [];
        foreach ($event->getWriteResults() as $writeResult) {
            $payload = $writeResult->getPayload();

            // still need to check if product sorting is active regardless of fields
            if (!\array_key_exists('fields', $payload) && (!\array_key_exists('active', $payload) || $payload['active'] !== true)) {
                continue;
            }

            $key = $writeResult->getPrimaryKey();
            \assert(\is_string($key));
            $productSortingIds[] = $key;
        }

        if (\count($productSortingIds) === 0) {
            return;
        }

        $customFieldNames = $this->connection->fetchFirstColumn(
            <<<'SQL'
                SELECT REPLACE(jt.field_value, 'customFields.', '') as field_name 
                FROM product_sorting
                CROSS JOIN JSON_TABLE(fields, '$[*]' COLUMNS (field_value VARCHAR(255) PATH '$.field')) AS jt 
                WHERE id IN (:ids) AND active = 1 AND jt.field_value LIKE :fields
                SQL,
            ['ids' => Uuid::fromHexToBytesList($productSortingIds), 'fields' => 'customFields.%'],
            ['ids' => ArrayParameterType::STRING]
        );

        $this->createFieldsInIndices($customFieldNames);
    }

    private function productStreamFilterWritten(EntityWrittenEvent $event): void
    {
        $filterIds = [];

        foreach ($event->getWriteResults() as $writeResult) {
            $key = $writeResult->getPrimaryKey();
            \assert(\is_string($key));
            $filterIds[] = $key;
        }

        $customFieldNames = $this->fetchCustomFieldNamesFromStreamFilters($filterIds);

        $this->createFieldsInIndices($customFieldNames);
    }

    /**
     * @param array<string> $filterIds
     *
     * @return array<string>
     */
    private function fetchCustomFieldNamesFromStreamFilters(array $filterIds): array
    {
        if (\count($filterIds) === 0) {
            return [];
        }

        return $this->connection->fetchFirstColumn(
            'SELECT REPLACE(field, \'customFields.\', \'\') FROM product_stream_filter WHERE id IN (:ids) AND field LIKE :field',
            ['ids' => Uuid::fromHexToBytesList($filterIds), 'field' => 'customFields.%'],
            ['ids' => ArrayParameterType::STRING]
        );
    }

    /**
     * @param array<string> $customFieldNames
     */
    private function createFieldsInIndices(array $customFieldNames): void
    {
        $customFieldNames = array_unique($customFieldNames);

        $customFields = $this->fetchCustomFieldsByName($customFieldNames);

        $fields = ElasticsearchCustomFieldsMappingHelper::mapCustomFieldsToEsTypes($customFields);

        $this->mappingHelper->createFieldsInIndices($fields);
    }

    /**
     * @param array<string> $fieldNames
     *
     * @return list<array{name: string, type: string}>
     */
    private function fetchCustomFieldsByName(array $fieldNames): array
    {
        if (\count($fieldNames) === 0) {
            return [];
        }

        $results = $this->connection->fetchAllAssociative(
            'SELECT name, type FROM custom_field WHERE name IN (:names)',
            ['names' => $fieldNames],
            ['names' => ArrayParameterType::STRING]
        );

        return array_map(static fn (array $row): array => [
            'name' => (string) $row['name'],
            'type' => (string) $row['type'],
        ], $results);
    }
}
