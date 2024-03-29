<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\LanguageLoaderInterface;
use Shopware\Elasticsearch\Product\CustomFieldUpdater;

#[Package('buyers-experience')]
class ElasticsearchFieldBuilder
{
    /**
     * @internal
     *
     * @param array<string, string> $languageAnalyzerMapping
     */
    public function __construct(
        private readonly LanguageLoaderInterface $languageLoader,
        private readonly ElasticsearchIndexingUtils $indexingUtils,
        private readonly array $languageAnalyzerMapping
    ) {
    }

    /**
     * @param array<string, mixed> $fieldConfig
     *
     * @description This method is used to build the mapping for translated fields
     *
     * @return array{properties: array<string, mixed>}
     */
    public function translated(array $fieldConfig): array
    {
        $languages = $this->languageLoader->loadLanguages();

        $languageFields = [];

        foreach ($languages as $languageId => $language) {
            $code = $language['code'] ?? $language['parentCode'];
            $parts = explode('-', $code);
            $locale = $parts[0];

            $languageFields[$languageId] = $fieldConfig;

            if (\array_key_exists($locale, $this->languageAnalyzerMapping)) {
                $languageFields[$languageId]['fields']['search']['analyzer'] = $this->languageAnalyzerMapping[$locale];
            }
        }

        return ['properties' => $languageFields];
    }

    /**
     * @description This method is used to build the mapping for translated custom fields
     *
     * @return array{ properties: array<string, array<string, string>> }
     */
    public function customFields(string $entity, Context $context): array
    {
        $languages = $this->languageLoader->loadLanguages();

        $customFields = [];

        foreach (array_keys($languages) as $languageId) {
            $customFields[$languageId] = $this->getCustomFieldsMapping($entity, $context);
        }

        return ['properties' => $customFields];
    }

    /**
     * @description This method is used to build the mapping for datetime fields
     *
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    public static function datetime(array $override = []): array
    {
        return array_merge([
            'type' => 'date',
            'format' => 'yyyy-MM-dd HH:mm:ss.000||strict_date_optional_time||epoch_millis',
            'ignore_malformed' => true,
        ], $override);
    }

    /**
     * @description This method is used to build the mapping for nested fields
     *
     * @param array<string, mixed> $properties
     *
     * @return array{type: 'nested', properties: array<string, mixed>}
     */
    public static function nested(array $properties = []): array
    {
        return [
            'type' => 'nested',
            'properties' => array_filter(array_merge([
                'id' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
                '_count' => AbstractElasticsearchDefinition::INT_FIELD,
            ], $properties)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getCustomFieldsMapping(string $entity, Context $context): array
    {
        $fieldMapping = $this->indexingUtils->getCustomFieldTypes($entity, $context);

        $mapping = [
            'type' => 'object',
            'dynamic' => true,
            'properties' => [],
        ];

        foreach ($fieldMapping as $name => $type) {
            /** @var array<mixed> $esType */
            $esType = CustomFieldUpdater::getTypeFromCustomFieldType($type);

            $mapping['properties'][$name] = $esType;
        }

        return $mapping;
    }
}
