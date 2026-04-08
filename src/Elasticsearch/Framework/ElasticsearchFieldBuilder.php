<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\LanguageLoaderInterface;
use Shopware\Elasticsearch\Product\ElasticsearchCustomFieldsMappingHelper;

#[Package('inventory')]
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
        return $this->buildTranslated($fieldConfig);
    }

    /**
     * @param array<string, mixed> $fieldConfig
     *
     * @return array{properties: array<string, mixed>}
     */
    public function translatedTechnicalTerms(array $fieldConfig): array
    {
        return $this->buildTranslated($fieldConfig, true);
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
            'format' => 'yyyy-MM-dd HH:mm:ss.SSS||strict_date_optional_time||epoch_millis',
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
     * @param array<string, mixed> $fieldConfig
     *
     * @return array{properties: array<string, mixed>}
     */
    private function buildTranslated(array $fieldConfig, bool $technicalTerms = false): array
    {
        $languages = $this->languageLoader->loadLanguages();

        $languageFields = [];

        foreach ($languages as $languageId => $language) {
            $code = $language['code'] ?? $language['parentCode'];
            $parts = explode('-', $code);
            $locale = $parts[0];

            $languageFields[$languageId] = $fieldConfig;

            if (!isset($languageFields[$languageId]['fields']['search']['analyzer'])) {
                continue;
            }

            if (!\array_key_exists($locale, $this->languageAnalyzerMapping)) {
                continue;
            }

            $analyzer = $this->languageAnalyzerMapping[$locale];

            if (!$technicalTerms) {
                $languageFields[$languageId]['fields']['search']['analyzer'] = $analyzer;

                continue;
            }

            $indexAnalyzer = $this->getTechnicalTermAnalyzer($analyzer, false);

            if ($indexAnalyzer !== null) {
                $languageFields[$languageId]['fields']['search']['analyzer'] = $indexAnalyzer;
            }

            if (!isset($languageFields[$languageId]['fields']['search']['search_analyzer'])) {
                continue;
            }

            $searchAnalyzer = $this->getTechnicalTermAnalyzer($analyzer, true);

            if ($searchAnalyzer === null) {
                continue;
            }

            $languageFields[$languageId]['fields']['search']['search_analyzer'] = $searchAnalyzer;
        }

        return ['properties' => $languageFields];
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
            $esType = ElasticsearchCustomFieldsMappingHelper::getTypeFromCustomFieldType($type);

            $mapping['properties'][$name] = $esType;
        }

        if ($mapping['properties'] === []) {
            unset($mapping['properties']);
        }

        return $mapping;
    }

    private function getTechnicalTermAnalyzer(string $analyzer, bool $searchAnalyzer): ?string
    {
        return match ($analyzer) {
            'sw_english_analyzer' => $searchAnalyzer ? 'sw_english_word_delimiter_search_analyzer' : 'sw_english_word_delimiter_index_analyzer',
            'sw_german_analyzer' => $searchAnalyzer ? 'sw_german_word_delimiter_search_analyzer' : 'sw_german_word_delimiter_index_analyzer',
            default => null,
        };
    }
}
