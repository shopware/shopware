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
     * Lowercase normalizer applied to every keyword field so case folding is
     * consistent across the index.
     */
    public const NORMALIZER_LOWERCASE = 'sw_lowercase_normalizer';

    /**
     * BM25 similarity profile with length normalisation enabled (`b=0.75`).
     * Applied to the `.search` subfield of long-form text fields where document
     * length should temper TF.
     */
    public const SIMILARITY_LENGTH_NORM = 'sw_length_norm';

    /**
     * Whitespace-only analyzer (whitespace tokenize + lowercase). The default
     * for `.exact` subfields and for the language-agnostic `.search` subfield.
     */
    public const ANALYZER_WHITESPACE = 'sw_whitespace_analyzer';

    /**
     * N-gram analyzer for the `.ngram` subfield. Substring matching for prefix
     * and partial-token queries. Min/max grams configured via
     * `SHOPWARE_ES_NGRAM_MIN_GRAM` / `SHOPWARE_ES_NGRAM_MAX_GRAM`.
     */
    public const ANALYZER_NGRAM = 'sw_ngram_analyzer';

    /**
     * Index-side technical-term analyzer (`word_delimiter_graph` chain) for
     * SKU-style fields where letter↔digit boundaries and `,` / `-` / `.`
     * separators must survive into the inverted index.
     */
    public const ANALYZER_WHITESPACE_TECHNICAL_INDEX = 'sw_whitespace_word_delimiter_index_analyzer';

    /**
     * Search-side counterpart of {@see self::ANALYZER_WHITESPACE_TECHNICAL_INDEX}
     * with the cross-position deduplication filter appended.
     */
    public const ANALYZER_WHITESPACE_TECHNICAL_SEARCH = 'sw_whitespace_word_delimiter_search_analyzer';

    /**
     * Common prefix of every language-agnostic analyzer this bundle ships.
     * Used by {@see self::translated()} to derive language-specific analyzer
     * names by string substitution (`sw_whitespace_…` → `sw_<lang>_…`).
     */
    public const ANALYZER_WHITESPACE_PREFIX = 'sw_whitespace_';

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
     * @description Build the mapping for translated fields.
     *
     * The method inspects the `.search` subfield analyzer of the input config
     * and substitutes the appropriate language-specific analyzer per language:
     *
     * - {@see self::ANALYZER_WHITESPACE} → the language analyzer (e.g. `sw_german_analyzer`).
     * - {@see self::ANALYZER_WHITESPACE_TECHNICAL_INDEX} → the language-specific
     *   word_delimiter pair (e.g. `sw_german_word_delimiter_{index,search}_analyzer`),
     *   when the language has them defined.
     *
     * `.exact` and `.ngram` subfields are language-agnostic and stay untouched.
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

            if (!isset($languageFields[$languageId]['fields']['search']['analyzer'])) {
                continue;
            }

            if (!\array_key_exists($locale, $this->languageAnalyzerMapping)) {
                continue;
            }

            $languageAnalyzer = $this->languageAnalyzerMapping[$locale];
            $currentAnalyzer = $languageFields[$languageId]['fields']['search']['analyzer'];

            if ($currentAnalyzer === self::ANALYZER_WHITESPACE_TECHNICAL_INDEX) {
                $indexAnalyzer = $this->getTechnicalTermAnalyzer($languageAnalyzer, false);
                $searchAnalyzer = $this->getTechnicalTermAnalyzer($languageAnalyzer, true);

                if ($indexAnalyzer !== null && $searchAnalyzer !== null) {
                    $languageFields[$languageId]['fields']['search']['analyzer'] = $indexAnalyzer;
                    $languageFields[$languageId]['fields']['search']['search_analyzer'] = $searchAnalyzer;
                }

                continue;
            }

            $languageFields[$languageId]['fields']['search']['analyzer'] = $languageAnalyzer;
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
