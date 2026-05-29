<?php declare(strict_types=1);

namespace Shopware\Elasticsearch;

use OpenSearchDSL\BuilderInterface;
use OpenSearchDSL\Query\Compound\BoolQuery;
use OpenSearchDSL\Query\Compound\DisMaxQuery;
use OpenSearchDSL\Query\FullText\MatchPhrasePrefixQuery;
use OpenSearchDSL\Query\FullText\MatchQuery;
use OpenSearchDSL\Query\TermLevel\TermQuery;
use OpenSearchDSL\Query\TermLevel\TermsQuery;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Elasticsearch\Framework\ElasticsearchFieldBuilder;
use Shopware\Elasticsearch\Product\SearchFieldConfig;
use Shopware\Elasticsearch\Query\MatchBoolPrefixQuery;

/**
 * @internal
 */
#[Package('inventory')]
class FieldQueryBuilder extends AbstractFieldQueryBuilder
{
    /**
     * @internal
     */
    public function __construct(
        private readonly int $minGram = 4,
        private readonly bool $useLanguageAnalyzer = true,
        private readonly float $dismaxTieBreaker = 0.2,
    ) {
    }

    public function getDecorated(): AbstractFieldQueryBuilder
    {
        throw new DecorationPatternException(self::class);
    }

    public function build(
        ResolvedField $field,
        string $token,
        SearchFieldConfig $config,
        Context $context,
    ): ?BuilderInterface {
        return $this->matchQuery($field->getResolvedField(), $token, $config);
    }

    private function matchQuery(Field $field, string $token, SearchFieldConfig $config): ?BuilderInterface
    {
        if ($this->isTextField($field)) {
            return $this->buildTextMatchQuery($token, $config);
        }

        $normalizedToken = $this->normalizeToken($token, $field);

        if ($normalizedToken === null) {
            return null;
        }

        return new TermQuery($config->getField(), $normalizedToken, ['boost' => $config->getRanking()]);
    }

    private function normalizeToken(string $token, Field $field): bool|int|float|string|null
    {
        if ($field instanceof BoolField) {
            return match ($token) {
                '1', 'true' => true,
                '0', 'false' => false,
                default => null,
            };
        }

        if ($field instanceof IntField || $field instanceof FloatField || $field instanceof PriceField) {
            if (!\is_numeric($token)) {
                return null;
            }

            return $field instanceof IntField ? (int) $token : (float) $token;
        }

        return $token;
    }

    private function isTextField(Field $field): bool
    {
        return $field instanceof StringField || $field instanceof LongTextField || $field instanceof ListField;
    }

    private function buildTextMatchQuery(string $token, SearchFieldConfig $config): BuilderInterface
    {
        $searchField = $config->getField() . '.search';
        $tokens = preg_split('/\s+/u', $token, -1, \PREG_SPLIT_NO_EMPTY) ?: [$token];
        $tokenCount = \count($tokens);
        $normalizedToken = $tokenCount > 1 ? implode(' ', $tokens) : $token;

        $lastWord = array_last($tokens);
        $maxExpansions = $this->getMaxExpansions($lastWord);

        $queries = array_values(array_filter([
            $this->buildExactMatchQuery($config, $tokens, $normalizedToken, $tokenCount),
            $this->buildFuzzyMatchQuery($searchField, $normalizedToken, $config, $maxExpansions),
            $this->buildPrefixMatchQuery($searchField, $normalizedToken, $config, $tokenCount, $maxExpansions),
            $this->buildNgramQuery($normalizedToken, $config, $tokenCount),
        ]));

        return $this->buildDisMaxQuery($queries, $config->getRanking());
    }

    /**
     * @param list<string> $tokens
     */
    private function buildExactMatchQuery(SearchFieldConfig $config, array $tokens, string $token, int $tokenCount): BuilderInterface
    {
        if ($tokenCount === 1) {
            if ($config->useExactSubfield()) {
                return new TermQuery($config->getField() . '.exact', $token, ['boost' => 1]);
            }

            $matchQueryParams = [
                'boost' => 1,
                'fuzziness' => 0,
                'operator' => 'and',
            ];

            if (!$this->useLanguageAnalyzer) {
                $matchQueryParams['analyzer'] = ElasticsearchFieldBuilder::ANALYZER_WHITESPACE;
            }

            return new MatchQuery($config->getField() . '.search', $token, $matchQueryParams);
        }

        if ($config->isAndLogic()) {
            $exactMatchQuery = new BoolQuery();

            foreach ($tokens as $tokenPart) {
                $exactMatchQuery->add(new TermQuery($config->getField(), $tokenPart), BoolQuery::MUST);
            }

            $exactMatchQuery->addParameter('boost', 1);

            return $exactMatchQuery;
        }

        return new TermsQuery($config->getField(), $tokens, ['boost' => 1]);
    }

    private function buildFuzzyMatchQuery(string $searchField, string $token, SearchFieldConfig $config, int $maxExpansions): MatchQuery
    {
        $matchQueryParams = [
            'boost' => 0.8,
            'fuzziness' => $config->getFuzziness($token),
            'operator' => $config->isAndLogic() ? 'and' : 'or',
            'fuzzy_transpositions' => true,
            'max_expansions' => $maxExpansions,
            'prefix_length' => 1,
        ];

        if (!$this->useLanguageAnalyzer) {
            $matchQueryParams['analyzer'] = ElasticsearchFieldBuilder::ANALYZER_WHITESPACE;
        }

        return new MatchQuery($searchField, $token, $matchQueryParams);
    }

    private function buildPrefixMatchQuery(
        string $searchField,
        string $token,
        SearchFieldConfig $config,
        int $tokenCount,
        int $maxExpansions,
    ): ?BuilderInterface {
        if (!$config->usePrefixMatch()) {
            return null;
        }

        if ($tokenCount > 1) {
            $matchPhrasePrefixParams = [
                'boost' => 0.6,
                'slop' => 3,
                'max_expansions' => $maxExpansions,
            ];

            if (!$this->useLanguageAnalyzer) {
                $matchPhrasePrefixParams['analyzer'] = ElasticsearchFieldBuilder::ANALYZER_WHITESPACE;
            }

            return new MatchPhrasePrefixQuery($searchField, $token, $matchPhrasePrefixParams);
        }

        $matchBoolPrefixParams = ['boost' => 0.4];

        if (!$this->useLanguageAnalyzer) {
            $matchBoolPrefixParams['analyzer'] = ElasticsearchFieldBuilder::ANALYZER_WHITESPACE;
        }

        return new MatchBoolPrefixQuery($searchField, $token, $matchBoolPrefixParams);
    }

    private function buildNgramQuery(string $token, SearchFieldConfig $config, int $tokenCount): ?MatchQuery
    {
        if (!$config->tokenize() || $tokenCount !== 1 || mb_strlen($token) < $this->minGram) {
            return null;
        }

        return new MatchQuery($config->getField() . '.ngram', $token, ['boost' => 0.4]);
    }

    /**
     * @param list<BuilderInterface> $queries
     */
    private function buildDisMaxQuery(array $queries, float|int $boost): DisMaxQuery
    {
        $dismax = new DisMaxQuery();

        foreach ($queries as $query) {
            $dismax->addQuery($query);
        }

        $dismax->addParameter('boost', $boost);
        $dismax->addParameter('tie_breaker', $this->dismaxTieBreaker);

        return $dismax;
    }

    private function getMaxExpansions(string $lastWord): int
    {
        $len = mb_strlen($lastWord);

        if ($len <= 3) {
            return 5;
        }

        if ($len <= 6) {
            return 10;
        }

        return 20;
    }
}
