<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework;

use OpenSearchDSL\Query\Compound\BoolQuery;
use OpenSearchDSL\Query\FullText\MatchPhrasePrefixQuery;
use OpenSearchDSL\Query\FullText\MatchQuery;
use OpenSearchDSL\Query\TermLevel\WildcardQuery;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
abstract class AbstractElasticsearchDefinition
{
    final public const KEYWORD_FIELD = [
        'type' => 'keyword',
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

    abstract public function getEntityDefinition(): EntityDefinition;

    /**
     * @return array{_source: array{includes: string[]}, properties: array<mixed>}
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

    /**
     * @deprecated tag:v6.6.0 - reason:return-type-change - Will become abstract, implementation should implement their own `buildTermQuery` and return type will change to BuilderInterface
     */
    public function buildTermQuery(Context $context, Criteria $criteria): BoolQuery
    {
        Feature::triggerDeprecationOrThrow(
            'ES_MULTILINGUAL_INDEX',
            'Will become abstract, implementation should implement their own `buildTermQuery`'
        );

        $bool = new BoolQuery();

        $term = (string) $criteria->getTerm();

        $queries = [
            new MatchQuery('fullTextBoosted', $term, ['boost' => 10]), // boosted word matches
            new MatchQuery('fullText', $term, ['boost' => 5]), // whole word matches
            new MatchQuery('fullText', $term, ['fuzziness' => 'auto', 'boost' => 3]), // word matches not exactly =>
            new MatchPhrasePrefixQuery('fullText', $term, ['boost' => 1, 'slop' => 5]), // one of the words begins with: "Spachtel" => "Spachtelmasse"
            new WildcardQuery('fullText', '*' . mb_strtolower($term) . '*'), // part of a word matches: "masse" => "Spachtelmasse"
            new MatchQuery('fullText.ngram', $term),
        ];

        foreach ($queries as $query) {
            $bool->add($query, BoolQuery::SHOULD);
        }

        $bool->addParameter('minimum_should_match', 1);

        return $bool;
    }

    /**
     * @deprecated tag:v6.6.0 - Use \Shopware\Elasticsearch\Framework\ElasticsearchIndexingUtils::stripText instead
     */
    protected function stripText(string $text): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0', 'Use \Shopware\Elasticsearch\Framework\ElasticsearchIndexingUtils::stripText instead')
        );

        return ElasticsearchIndexingUtils::stripText($text);
    }

    /**
     * @param array<int, array<string, string>> $items
     *
     * @return array<string, string|null>
     *
     * @deprecated tag:v6.6.0 - Use \Shopware\Elasticsearch\Framework\ElasticsearchFieldMapper::translated instead
     */
    protected function mapTranslatedField(string $field, bool $stripText = true, ...$items): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0', 'Use \Shopware\Elasticsearch\Framework\ElasticsearchFieldMapper::translated instead')
        );

        return ElasticsearchFieldMapper::translated(field: $field, items: array_merge(...$items), stripText: $stripText);
    }

    /**
     * @param array<int, array{id: string, languageId?: string}> $items
     * @param string[] $translatedFields
     *
     * @return array<int, array<string, array<string, string>>>
     *
     * @deprecated tag:v6.6.0 - Use \Shopware\Elasticsearch\Framework\ElasticsearchFieldMapper::toManyAssociations instead
     */
    protected function mapToManyAssociations(array $items, array $translatedFields): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0', 'Use \Shopware\Elasticsearch\Framework\ElasticsearchFieldMapper::toManyAssociations instead')
        );

        return ElasticsearchFieldMapper::toManyAssociations($items, $translatedFields);
    }

    /**
     * @param array<int, array{id: string, languageId?: string}> $items
     * @param string[] $translatedFields
     *
     * @return array<string, array<string, string>>
     *
     * @deprecated tag:v6.6.0 - will be removed
     */
    protected function mapToOneAssociations(array $items, array $translatedFields): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0', 'will be removed')
        );

        if (!Feature::isActive('v6.6.0.0')) {
            $result = [];

            foreach ($items as $item) {
                if (empty($item['languageId'])) {
                    continue;
                }

                foreach ($translatedFields as $field) {
                    if (empty($item[$field])) {
                        continue;
                    }

                    $result[$field][$item['languageId']] = $this->stripText($item[$field]);
                }
            }

            return $result;
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function getTextFieldConfig(): array
    {
        return self::KEYWORD_FIELD + self::SEARCH_FIELD;
    }
}
