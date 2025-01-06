<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use OpenSearchDSL\Query\Compound\BoolQuery;
use OpenSearchDSL\Query\Compound\DisMaxQuery;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Filter\AbstractTokenFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\TokenizerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Elasticsearch\ElasticsearchException;
use Shopware\Elasticsearch\TokenQueryBuilder;

/**
 * @phpstan-type SearchConfig array{and_logic: string, field: string, tokenize: int, ranking: int}
 */
#[Package('core')]
class ProductSearchQueryBuilder extends AbstractProductSearchQueryBuilder
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityDefinition $productDefinition,
        private readonly AbstractTokenFilter $tokenFilter,
        private readonly TokenizerInterface $tokenizer,
        private readonly SearchConfigLoader $configLoader,
        private readonly TokenQueryBuilder $tokenQueryBuilder
    ) {
    }

    public function getDecorated(): AbstractProductSearchQueryBuilder
    {
        throw new DecorationPatternException(self::class);
    }

    public function build(Criteria $criteria, Context $context): BoolQuery
    {
        $originalTerm = mb_strtolower((string) $criteria->getTerm());

        $tokens = $this->tokenizer->tokenize($originalTerm);
        $tokens = $this->tokenFilter->filter($tokens, $context);

        if (empty(array_filter($tokens))) {
            throw ElasticsearchException::emptyQuery();
        }

        $searchConfig = $this->configLoader->load($context);

        $configs = array_map(function (array $item): SearchFieldConfig {
            return new SearchFieldConfig(
                (string) $item['field'],
                (float) $item['ranking'],
                (bool) $item['tokenize'],
                (bool) $item['and_logic'],
            );
        }, $searchConfig);

        if (!$configs[0]->isAndLogic()) {
            $tokens = [$originalTerm];
        }

        $queries = [];

        foreach ($tokens as $token) {
            $query = $this->tokenQueryBuilder->build(
                $this->productDefinition->getEntityName(),
                $token,
                $configs,
                $context,
            );

            if ($query) {
                $queries[] = $query;
            }
        }

        if (empty($queries)) {
            throw ElasticsearchException::emptyQuery();
        }

        if (\count($queries) === 1 && $queries[0] instanceof BoolQuery) {
            return $queries[0];
        }

        $andSearch = $configs[0]->isAndLogic() ? BoolQuery::MUST : BoolQuery::SHOULD;

        $tokensQuery = new BoolQuery([$andSearch => $queries]);

        if (\in_array($originalTerm, $tokens, true)) {
            return $tokensQuery;
        }

        $originalTermQuery = $this->tokenQueryBuilder->build(
            $this->productDefinition->getEntityName(),
            $originalTerm,
            $configs,
            $context
        );

        if (!$originalTermQuery) {
            return $tokensQuery;
        }

        $dismax = new DisMaxQuery();

        $dismax->addQuery($tokensQuery);
        $dismax->addQuery($originalTermQuery);

        // @deprecated tag:v6.7.0 - will just return $dismax when return type is changed to BuilderInterface
        return new BoolQuery([
            BoolQuery::MUST => [$dismax],
        ]);
    }
}
