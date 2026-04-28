<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework;

use OpenSearchDSL\BuilderInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
abstract class AbstractElasticsearchDefinition
{
    final public const KEYWORD_FIELD = [
        'type' => 'keyword',
        'ignore_above' => 10000,
        'normalizer' => ElasticsearchFieldBuilder::NORMALIZER_LOWERCASE,
    ];

    final public const BOOLEAN_FIELD = ['type' => 'boolean'];

    final public const FLOAT_FIELD = ['type' => 'double'];

    final public const INT_FIELD = ['type' => 'long'];

    final public const SEARCH_FIELD = [
        'fields' => [
            'search' => ['type' => 'text', 'analyzer' => ElasticsearchFieldBuilder::ANALYZER_WHITESPACE],
            'ngram' => ['type' => 'text', 'analyzer' => ElasticsearchFieldBuilder::ANALYZER_NGRAM],
        ],
    ];

    final public const SEARCH_FIELD_WITH_EXACT = [
        'fields' => [
            'exact' => [
                'type' => 'text',
                'analyzer' => ElasticsearchFieldBuilder::ANALYZER_WHITESPACE,
                'search_analyzer' => ElasticsearchFieldBuilder::ANALYZER_WHITESPACE,
                'norms' => false,
            ],
            'search' => ['type' => 'text', 'analyzer' => ElasticsearchFieldBuilder::ANALYZER_WHITESPACE],
            'ngram' => ['type' => 'text', 'analyzer' => ElasticsearchFieldBuilder::ANALYZER_NGRAM],
        ],
    ];

    final public const SEARCH_FIELD_WITH_LENGTH_NORM = [
        'fields' => [
            'search' => [
                'type' => 'text',
                'analyzer' => ElasticsearchFieldBuilder::ANALYZER_WHITESPACE,
                'similarity' => ElasticsearchFieldBuilder::SIMILARITY_LENGTH_NORM,
            ],
            'ngram' => ['type' => 'text', 'analyzer' => ElasticsearchFieldBuilder::ANALYZER_NGRAM],
        ],
    ];

    final public const TECHNICAL_TERM_SEARCH_FIELD = [
        'fields' => [
            'search' => [
                'type' => 'text',
                'analyzer' => ElasticsearchFieldBuilder::ANALYZER_WHITESPACE_TECHNICAL_INDEX,
                'search_analyzer' => ElasticsearchFieldBuilder::ANALYZER_WHITESPACE_TECHNICAL_SEARCH,
            ],
            'ngram' => ['type' => 'text', 'analyzer' => ElasticsearchFieldBuilder::ANALYZER_NGRAM],
        ],
    ];

    abstract public function getEntityDefinition(): EntityDefinition;

    /**
     * @return array{_source?: array{includes: string[]}, properties: array<mixed>}
     */
    abstract public function getMapping(Context $context): array;

    /**
     * Can be used to define custom queries to define the data to be indexed.
     */
    public function getIterator(): ?IterableQuery
    {
        return null;
    }

    /**
     * @param array<string> $ids
     *
     * @return array<string, array<string, mixed>>
     */
    public function fetch(array $ids, Context $context): array
    {
        return [];
    }

    abstract public function buildTermQuery(Context $context, Criteria $criteria): BuilderInterface;

    /**
     * @deprecated tag:v6.8.0 - reason:becomes-internal - Use {@see self::buildTextFieldConfig()} instead.
     *
     * @return array<string, mixed>
     */
    protected static function getTextFieldConfig(): array
    {
        return self::KEYWORD_FIELD + self::SEARCH_FIELD;
    }

    /**
     * Returns text field config. Flags:
     * - `$withExact`: add an unanalyzed `exact` subfield for high-boost exact-token matching.
     * - `$technicalTerms`: route the `search` subfield through the `word_delimiter_graph`
     *   analyzer pair so SKUs, manufacturer numbers, and EANs keep their letter+digit
     *   boundaries and survive `,` / `-` / `.` separators.
     * - `$lengthNorm`: apply BM25 length normalization (`sw_length_norm`, b=0.75) on the
     *   `search` subfield, for long merchant-curated fields like `customSearchKeywords`
     *   where document length is a relevance signal.
     *
     * @return array<string, mixed>
     */
    protected static function buildTextFieldConfig(bool $withExact = false, bool $technicalTerms = false, bool $lengthNorm = false): array
    {
        $fieldConfig = $technicalTerms ? self::TECHNICAL_TERM_SEARCH_FIELD : self::SEARCH_FIELD;

        if ($lengthNorm) {
            $fieldConfig['fields']['search']['similarity'] = 'sw_length_norm';
        }

        if ($withExact) {
            $fieldConfig['fields'] = ['exact' => self::SEARCH_FIELD_WITH_EXACT['fields']['exact']] + $fieldConfig['fields'];
        }

        return self::KEYWORD_FIELD + $fieldConfig;
    }

    /**
     * Returns text field config with BM25 length normalization (b=0.75) for long-form text fields
     * like description and metaDescription, where document length IS a relevance signal.
     *
     * @return array<string, mixed>
     */
    protected static function getTextFieldWithLengthNormConfig(): array
    {
        return self::KEYWORD_FIELD + self::SEARCH_FIELD_WITH_LENGTH_NORM;
    }
}
