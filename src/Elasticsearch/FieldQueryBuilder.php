<?php declare(strict_types=1);

namespace Shopware\Elasticsearch;

use OpenSearchDSL\BuilderInterface;
use OpenSearchDSL\Query\Compound\ConstantScoreQuery;
use OpenSearchDSL\Query\Compound\DisMaxQuery;
use OpenSearchDSL\Query\FullText\MatchPhrasePrefixQuery;
use OpenSearchDSL\Query\FullText\MatchQuery;
use OpenSearchDSL\Query\TermLevel\TermQuery;
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
 *
 * Builds the per-field query for a SINGLE search token. Splitting the user's input into
 * tokens is the caller's job ({@see Product\ProductSearchQueryBuilder}); this builder never
 * re-splits. Multi-word proximity is handled separately: when the config is a "phrase" config
 * ({@see SearchFieldConfig::isPhrase()}) the whole term is matched as a match_phrase_prefix,
 * which the caller adds as a SHOULD boost on top of the per-token queries.
 *
 * Per-match-type boosts are injected from `elasticsearch.search.boost.*` so relevance can be
 * tuned via configuration. In explain mode ({@see Context::ELASTICSEARCH_EXPLAIN_MODE}) each
 * clause is named with its match type so the live-search preview can report how a field matched.
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
        private readonly float $exactBoost = 2.0,
        private readonly float $phraseBoost = 4.0,
        private readonly float $fuzzyBoost = 0.4,
        private readonly float $prefixBoost = 0.4,
        private readonly float $partialBoost = 0.4,
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
        return $this->matchQuery($field->getResolvedField(), $token, $config, $context);
    }

    private function matchQuery(Field $field, string $token, SearchFieldConfig $config, Context $context): ?BuilderInterface
    {
        if ($this->isTextField($field)) {
            return $config->isPhrase()
                ? $this->buildPhraseMatchQuery($token, $config, $context)
                : $this->buildTextMatchQuery($token, $config, $context);
        }

        // A phrase boost is a multi-word proximity signal — it has no meaning for
        // non-text (numeric/bool) fields, so those contribute nothing to it.
        if ($config->isPhrase()) {
            return null;
        }

        $normalizedToken = $this->normalizeToken($token, $field);

        if ($normalizedToken === null) {
            return null;
        }

        $term = new TermQuery($config->getField(), $normalizedToken, ['boost' => $config->getRanking()]);
        $this->nameClause($term, $config, (string) $normalizedToken, 'exact', $context);

        return $term;
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

    private function buildTextMatchQuery(string $token, SearchFieldConfig $config, Context $context): BuilderInterface
    {
        $searchField = $config->getField() . '.search';
        $maxExpansions = $this->getMaxExpansions($token);

        $clauses = [
            'exact' => $this->buildExactMatchQuery($config, $token),
            'fuzzy' => $this->buildFuzzyMatchQuery($searchField, $token, $config, $maxExpansions),
            'prefix' => $this->buildPrefixMatchQuery($searchField, $token, $config),
            'ngram' => $this->buildNgramQuery($token, $config),
        ];

        foreach ($clauses as $type => $clause) {
            if ($clause !== null) {
                $this->nameClause($clause, $config, $token, $type, $context);
            }
        }

        return $this->buildDisMaxQuery(array_values(array_filter($clauses)), $config->getRanking());
    }

    /**
     * Explicit multi-word proximity boost. Runs on the analyzed `.search` subfield as a
     * match_phrase_prefix so word order/adjacency is rewarded and the last word may still be
     * a prefix (search-as-you-type). A phrase match requires every word to be present, so
     * when the caller adds this as a SHOULD it can only re-rank AND matches, never admit new
     * ones — AND semantics stay intact.
     */
    private function buildPhraseMatchQuery(string $token, SearchFieldConfig $config, Context $context): ?MatchPhrasePrefixQuery
    {
        if (!$config->usePrefixMatch()) {
            return null;
        }

        $lastWord = array_last(preg_split('/\s+/u', $token, -1, \PREG_SPLIT_NO_EMPTY) ?: [$token]);

        // The phrase boost is folded with the field ranking, since the caller adds this as a
        // standalone SHOULD clause rather than a member of the per-field DisMax.
        $params = [
            'boost' => $this->phraseBoost * $config->getRanking(),
            'slop' => 3,
            'max_expansions' => $this->getMaxExpansions((string) $lastWord),
        ];

        if (!$this->useLanguageAnalyzer) {
            $params['analyzer'] = ElasticsearchFieldBuilder::ANALYZER_WHITESPACE;
        }

        $phrase = new MatchPhrasePrefixQuery($config->getField() . '.search', $token, $params);
        $this->nameClause($phrase, $config, $token, 'phrase', $context);

        return $phrase;
    }

    private function buildExactMatchQuery(SearchFieldConfig $config, string $token): BuilderInterface
    {
        if ($config->useExactSubfield()) {
            return new TermQuery($config->getField() . '.exact', $token, ['boost' => $this->exactBoost]);
        }

        $matchQueryParams = [
            'boost' => $this->exactBoost,
            'fuzziness' => 0,
            'operator' => 'and',
        ];

        if (!$this->useLanguageAnalyzer) {
            $matchQueryParams['analyzer'] = ElasticsearchFieldBuilder::ANALYZER_WHITESPACE;
        }

        return new MatchQuery($config->getField() . '.search', $token, $matchQueryParams);
    }

    private function buildFuzzyMatchQuery(string $searchField, string $token, SearchFieldConfig $config, int $maxExpansions): MatchQuery
    {
        $matchQueryParams = [
            'boost' => $this->fuzzyBoost,
            'fuzziness' => $config->getFuzziness($token),
            'operator' => $config->isAndLogic() ? 'and' : 'or',
            'fuzzy_transpositions' => true,
            'max_expansions' => $maxExpansions,
            'prefix_length' => $config->getPrefixLength($token),
        ];

        if (!$this->useLanguageAnalyzer) {
            $matchQueryParams['analyzer'] = ElasticsearchFieldBuilder::ANALYZER_WHITESPACE;
        }

        return new MatchQuery($searchField, $token, $matchQueryParams);
    }

    private function buildPrefixMatchQuery(string $searchField, string $token, SearchFieldConfig $config): ?BuilderInterface
    {
        if (!$config->usePrefixMatch()) {
            return null;
        }

        $matchBoolPrefixParams = ['boost' => $this->prefixBoost];

        if (!$this->useLanguageAnalyzer) {
            $matchBoolPrefixParams['analyzer'] = ElasticsearchFieldBuilder::ANALYZER_WHITESPACE;
        }

        return new MatchBoolPrefixQuery($searchField, $token, $matchBoolPrefixParams);
    }

    private function buildNgramQuery(string $token, SearchFieldConfig $config): ?BuilderInterface
    {
        if (!$config->tokenize() || mb_strlen($token) < $this->minGram) {
            return null;
        }

        // n-gram is the weakest, fragment-level fallback: it matches on shared
        // character n-grams. Because it is scored with BM25, a token whose only
        // shared fragment is rare (high idf) can out-score a real word match — a
        // misspelling like "mabrle" then ranks an unrelated product that merely
        // shares the "abr" fragment above the actual fuzzy "marble" corrections.
        // Wrap it in constant_score so every n-gram hit contributes the same
        // fixed, low weight: enough to surface fragment matches for recall, never
        // enough to outrank an exact / fuzzy / prefix word match.
        return new ConstantScoreQuery(
            new MatchQuery($config->getField() . '.ngram', $token),
            ['boost' => $this->partialBoost],
        );
    }

    /**
     * In explain mode, tag a clause with its match type so the live-search preview can report
     * how the field matched. Gated on the state, so normal search is untouched.
     */
    private function nameClause(BuilderInterface $clause, SearchFieldConfig $config, string $term, string $type, Context $context): void
    {
        if (!$context->hasState(Context::ELASTICSEARCH_EXPLAIN_MODE) || !method_exists($clause, 'addParameter')) {
            return;
        }

        $clause->addParameter('_name', (string) json_encode([
            'field' => $config->getField(),
            'term' => $term,
            'ranking' => $config->getRanking(),
            'type' => $type,
        ]));
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
