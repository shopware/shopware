<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use OpenSearch\Client;
use OpenSearch\Common\Exceptions\BadRequest400Exception;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Shopware\Elasticsearch\Framework\ElasticsearchOutdatedIndexDetector;

/**
 * @internal
 */
#[Package('inventory')]
class ElasticsearchCustomFieldsMappingHelper
{
    public function __construct(
        private readonly ElasticsearchOutdatedIndexDetector $indexDetector,
        private readonly Client $client,
        private readonly CustomFieldSetGateway $customFieldSetGateway
    ) {
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
                'format' => 'yyyy-MM-dd HH:mm:ss.SSS||strict_date_optional_time||epoch_millis',
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
     * @param array<string, string> $customFields
     *
     * @return array<string, array{type: string}>
     */
    public static function mapCustomFieldsToEsTypes(array $customFields): array
    {
        $esTypes = [];
        foreach ($customFields as $name => $type) {
            $esTypes[$name] = self::getTypeFromCustomFieldType($type);
        }

        return $esTypes;
    }

    /**
     * @param array<string, array<mixed>> $newCreatedFields
     */
    public function createFieldsInIndices(array $newCreatedFields): void
    {
        if ($newCreatedFields === []) {
            return;
        }

        $indices = $this->indexDetector->getAllUsedIndices();
        if ($indices === []) {
            return;
        }

        $languageIds = $this->customFieldSetGateway->fetchLanguageIds();
        if ($languageIds === []) {
            return;
        }

        $this->createFieldsInIndicesWithLanguages($newCreatedFields, $indices, $languageIds);
    }

    /**
     * @param array<string, array<mixed>> $newCreatedFields
     * @param array<string> $indices
     * @param array<string> $languageIds
     */
    public function createFieldsInIndicesWithLanguages(array $newCreatedFields, array $indices, array $languageIds): void
    {
        if ($newCreatedFields === []) {
            return;
        }

        foreach ($indices as $indexName) {
            $body = [
                'properties' => [
                    'customFields' => [
                        'properties' => [],
                    ],
                ],
            ];

            foreach ($languageIds as $languageId) {
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
}
