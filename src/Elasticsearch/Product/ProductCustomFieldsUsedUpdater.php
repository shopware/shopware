<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingDefinition;
use Shopware\Core\Content\ProductStream\Aggregate\ProductStreamFilter\ProductStreamFilterDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('inventory')]
class ProductCustomFieldsUsedUpdater implements EventSubscriberInterface
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
            ProductSortingDefinition::ENTITY_NAME . '.written' => 'productSortingWritten',
            ProductStreamFilterDefinition::ENTITY_NAME . '.written' => 'productStreamFilterWritten',
        ];
    }

    public function productSortingWritten(EntityWrittenEvent $event): void
    {
        if (!$this->elasticsearchHelper->allowIndexing()) {
            return;
        }

        $productSortingIds = [];

        foreach ($event->getWriteResults() as $writeResult) {
            $payload = $writeResult->getPayload();

            if (!\array_key_exists('fields', $payload)) {
                continue;
            }

            $key = $writeResult->getPrimaryKey();
            if (!\is_string($key)) {
                continue;
            }

            $productSortingIds[] = $key;
        }

        if ($productSortingIds === []) {
            return;
        }

        $customFieldNames = $this->fetchCustomFieldNamesFromSortings($productSortingIds);

        $this->createFieldsInIndices($customFieldNames);
    }

    public function productStreamFilterWritten(EntityWrittenEvent $event): void
    {
        if (!$this->elasticsearchHelper->allowIndexing()) {
            return;
        }

        $productStreamFilterIds = [];

        foreach ($event->getWriteResults() as $writeResult) {
            if (!\in_array($writeResult->getOperation(), [
                EntityWriteResult::OPERATION_INSERT,
                EntityWriteResult::OPERATION_UPDATE,
            ], true)) {
                continue;
            }

            $key = $writeResult->getPrimaryKey();
            if (!\is_string($key)) {
                continue;
            }

            $productStreamFilterIds[] = $key;
        }

        if ($productStreamFilterIds === []) {
            return;
        }

        $customFieldNames = $this->fetchCustomFieldNamesFromStreamFilters($productStreamFilterIds);

        $this->createFieldsInIndices($customFieldNames);
    }

    /**
     * @param list<string> $productSortingIds
     *
     * @return list<string>
     */
    private function fetchCustomFieldNamesFromSortings(array $productSortingIds): array
    {
        $rows = $this->connection->fetchFirstColumn(
            'SELECT fields FROM product_sorting WHERE id IN (:ids) AND fields LIKE :pattern',
            ['ids' => Uuid::fromHexToBytesList($productSortingIds), 'pattern' => '%customFields.%'],
            ['ids' => ArrayParameterType::STRING]
        );

        $customFieldNames = [];
        $prefixLength = \strlen('customFields.');

        foreach ($rows as $row) {
            try {
                $fields = json_decode((string) $row, true, 512, \JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                continue;
            }

            if (!\is_array($fields)) {
                continue;
            }

            foreach ($fields as $field) {
                $name = $field['field'] ?? null;

                if (\is_string($name) && str_starts_with($name, 'customFields.')) {
                    $customFieldNames[substr($name, $prefixLength)] = true;
                }
            }
        }

        return array_keys($customFieldNames);
    }

    /**
     * @param list<string> $productStreamFilterIds
     *
     * @return list<string>
     */
    private function fetchCustomFieldNamesFromStreamFilters(array $productStreamFilterIds): array
    {
        return $this->connection->fetchFirstColumn(
            'SELECT DISTINCT REPLACE(field, \'customFields.\', \'\') FROM product_stream_filter WHERE id IN (:ids) AND field LIKE :field',
            ['ids' => Uuid::fromHexToBytesList($productStreamFilterIds), 'field' => 'customFields.%'],
            ['ids' => ArrayParameterType::STRING]
        );
    }

    /**
     * @param list<string> $customFieldNames
     */
    private function createFieldsInIndices(array $customFieldNames): void
    {
        $customFieldNames = array_values(array_unique($customFieldNames));

        $customFieldTypes = $this->fetchCustomFieldTypesByName($customFieldNames);

        $fields = ElasticsearchCustomFieldsMappingHelper::mapCustomFieldsToEsTypes($customFieldTypes);

        $this->mappingHelper->createFieldsInIndices($fields);
    }

    /**
     * @param array<string> $fieldNames
     *
     * @return array<string, string>
     */
    private function fetchCustomFieldTypesByName(array $fieldNames): array
    {
        if ($fieldNames === []) {
            return [];
        }

        $result = $this->connection->fetchAllKeyValue(
            'SELECT name, type FROM custom_field WHERE name IN (:names)',
            ['names' => $fieldNames],
            ['names' => ArrayParameterType::STRING]
        );

        return $result;
    }
}
