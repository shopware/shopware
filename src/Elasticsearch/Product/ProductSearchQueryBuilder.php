<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use OpenSearchDSL\BuilderInterface;
use OpenSearchDSL\Query\Compound\BoolQuery;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\SearchConfigLoader;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Filter\AbstractTokenFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Elasticsearch\AbstractTokenQueryBuilder;
use Shopware\Elasticsearch\ElasticsearchException;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchTokenizer;

/**
 * @phpstan-type SearchConfig array{and_logic: string, field: string, tokenize: int, ranking: int, use_exact_subfield?: int}
 */
#[Package('framework')]
class ProductSearchQueryBuilder extends AbstractProductSearchQueryBuilder
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityDefinition $productDefinition,
        private readonly AbstractTokenFilter $tokenFilter,
        private readonly SearchConfigLoader $configLoader,
        private readonly AbstractTokenQueryBuilder $tokenQueryBuilder,
        private readonly ElasticsearchTokenizer $tokenizer,
    ) {
    }

    public function getDecorated(): AbstractProductSearchQueryBuilder
    {
        throw new DecorationPatternException(self::class);
    }

    public function build(Criteria $criteria, Context $context): BuilderInterface
    {
        $originalTerm = mb_strtolower((string) $criteria->getTerm());

        $searchConfig = $this->configLoader->load($context);

        $minSearchLength = $searchConfig[0]['min_search_length'] ?? AbstractTokenFilter::DEFAULT_MIN_SEARCH_TERM_LENGTH;
        $tokens = $this->tokenizer->tokenize($originalTerm, $minSearchLength);
        $tokens = $this->tokenFilter->filter($tokens, $context);

        if (array_filter($tokens) === []) {
            throw ElasticsearchException::emptyQuery();
        }

        $configs = array_map(static function (array $item): SearchFieldConfig {
            return new SearchFieldConfig(
                $item['field'],
                $item['ranking'],
                (bool) $item['tokenize'],
                (bool) $item['and_logic'],
                true,
                (bool) $item['use_exact_subfield'],
            );
        }, $searchConfig);

        // For an OR multi-word search, keep n-gram (substring) matching off: a single query word
        // matching inside an unrelated word (e.g. "line" in "Portaline") is noise. n-gram stays on
        // for single-word searches and for AND, where all words must match anyway.
        if (\count($tokens) > 1 && !$configs[0]->isAndLogic()) {
            $configs = array_map(static fn (SearchFieldConfig $config): SearchFieldConfig => $config->withoutNgram(), $configs);
        }

        $entity = $this->productDefinition->getEntityName();

        // One query per token. The Elasticsearch analyzer already tokenizes both the indexed
        // data and each token consistently, so we never re-split below this point.
        $tokenQueries = [];
        foreach ($tokens as $token) {
            $query = $this->tokenQueryBuilder->build($entity, $token, $configs, $context);

            if ($query) {
                $tokenQueries[] = $query;
            }
        }

        if ($tokenQueries === []) {
            throw ElasticsearchException::emptyQuery();
        }

        // A single token needs no AND/OR wrapper and has no phrase to boost.
        if (\count($tokenQueries) === 1) {
            return $tokenQueries[0];
        }

        // AND requires every token to match (MUST); OR requires any (SHOULD). Commercial's
        // strictness (minimum_should_match) layers on top of the SHOULD case in its own builder.
        $gate = $configs[0]->isAndLogic() ? BoolQuery::MUST : BoolQuery::SHOULD;
        $query = new BoolQuery([$gate => $tokenQueries]);

        // Multi-word input also rewards documents where the words appear together, added as an
        // explicit SHOULD boost. A phrase match requires every word to be present, so it can
        // only re-rank documents that already satisfy the gate above — it never loosens AND.
        $phraseQuery = $this->tokenQueryBuilder->build(
            $entity,
            $originalTerm,
            array_map(static fn (SearchFieldConfig $config): SearchFieldConfig => $config->withPhrase(), $configs),
            $context,
        );

        if ($phraseQuery) {
            $query->add($phraseQuery, BoolQuery::SHOULD);
        }

        return $query;
    }
}
