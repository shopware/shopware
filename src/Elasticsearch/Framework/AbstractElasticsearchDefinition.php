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
        'normalizer' => 'sw_lowercase_normalizer',
    ];

    final public const BOOLEAN_FIELD = ['type' => 'boolean'];

    final public const FLOAT_FIELD = ['type' => 'double'];

    final public const INT_FIELD = ['type' => 'long'];

    final public const SEARCH_FIELD = [
        'fields' => [
            'search' => ['type' => 'text', 'analyzer' => 'sw_whitespace_analyzer'],
            'ngram' => ['type' => 'text', 'analyzer' => 'sw_ngram_analyzer'],
        ],
    ];

    final public const SEARCH_FIELD_WITH_LENGTH_NORM = [
        'fields' => [
            'search' => ['type' => 'text', 'analyzer' => 'sw_whitespace_analyzer', 'similarity' => 'sw_length_norm'],
            'ngram' => ['type' => 'text', 'analyzer' => 'sw_ngram_analyzer'],
        ],
    ];

    final public const TECHNICAL_TERM_SEARCH_FIELD = [
        'fields' => [
            'search' => [
                'type' => 'text',
                'analyzer' => 'sw_whitespace_word_delimiter_index_analyzer',
                'search_analyzer' => 'sw_whitespace_word_delimiter_search_analyzer',
            ],
            'ngram' => ['type' => 'text', 'analyzer' => 'sw_ngram_analyzer'],
        ],
    ];

    final public const EXACT_FIELD = [
        'exact' => [
            'type' => 'text',
            'analyzer' => 'sw_whitespace_analyzer',
            'search_analyzer' => 'sw_whitespace_analyzer',
            'norms' => false,
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
     * @return array<string, mixed>
     */
    protected static function getTextFieldConfig(bool $withExact = false, bool $technicalTerms = false, bool $lengthNorm = false): array
    {
        $fieldConfig = $technicalTerms ? self::TECHNICAL_TERM_SEARCH_FIELD : self::SEARCH_FIELD;

        if ($lengthNorm) {
            $fieldConfig['fields']['search']['similarity'] = 'sw_length_norm';
        }

        if ($withExact) {
            $fieldConfig['fields'] = self::EXACT_FIELD + $fieldConfig['fields'];
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
