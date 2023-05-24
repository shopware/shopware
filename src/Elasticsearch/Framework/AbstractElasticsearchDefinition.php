<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework;

use OpenSearchDSL\Query\Compound\BoolQuery;
use OpenSearchDSL\Query\FullText\MatchPhrasePrefixQuery;
use OpenSearchDSL\Query\FullText\MatchQuery;
use OpenSearchDSL\Query\TermLevel\WildcardQuery;
use Shopware\Core\Framework\Context;
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
            'search' => ['type' => 'text'],
            'ngram' => ['type' => 'text', 'analyzer' => 'sw_ngram_analyzer'],
        ],
    ];

    abstract public function getEntityDefinition(): EntityDefinition;

    /**
     * @return array<mixed>
     */
    abstract public function getMapping(Context $context): array;

    /**
     * @param array<string> $ids
     *
     * @return array<mixed>
     */
    public function fetch(array $ids, Context $context): array
    {
        return [];
    }

    /**
     * @deprecated tag:v6.6.0 - Will become abstract, implementation should implement their own `buildTermQuery`
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

    protected function stripText(string $text): string
    {
        // Remove all html elements to save up space
        $text = strip_tags($text);

        if (mb_strlen($text) >= 32766) {
            return mb_substr($text, 0, 32766);
        }

        return $text;
    }

    /**
     * @param array<int, array<string, string>> $items
     *
     * @return array<int|string, mixed>
     */
    protected function mapTranslatedField(string $field, bool $stripText = true, ...$items): array
    {
        $value = [];

        foreach ($items as $item) {
            if (empty($item['languageId'])) {
                continue;
            }
            $languageId = $item['languageId'];
            $newValue = $item[$field] ?? null;

            if ($stripText && \is_string($newValue)) {
                $newValue = $this->stripText($newValue);
            }

            // if child value is null, it should be inherited from parent
            $value[$languageId] = $newValue === null ? ($value[$languageId] ?? '') : $newValue;
        }

        return $value;
    }
}
