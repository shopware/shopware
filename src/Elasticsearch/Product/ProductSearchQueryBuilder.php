<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use OpenSearchDSL\Query\Compound\BoolQuery;
use OpenSearchDSL\Query\FullText\MatchPhrasePrefixQuery;
use OpenSearchDSL\Query\FullText\MatchQuery;
use OpenSearchDSL\Query\Joining\NestedQuery;
use OpenSearchDSL\Query\TermLevel\WildcardQuery;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Filter\AbstractTokenFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Tokenizer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @phpstan-type SearchConfig array{and_logic: string, field: string, tokenize: int, ranking: int}
 */
#[Package('core')]
class ProductSearchQueryBuilder extends AbstractProductSearchQueryBuilder
{
    private const NOT_SUPPORTED_FIELDS = [
        'manufacturer.customFields',
        'categories.customFields',
    ];

    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly Tokenizer $tokenizer,
        private readonly AbstractTokenFilter $tokenFilter
    ) {
    }

    public function build(Criteria $criteria, Context $context): BoolQuery
    {
        $bool = new BoolQuery();

        $searchConfig = $this->fetchConfig($context);

        $isAndSearch = $searchConfig[0]['and_logic'] === '1';

        $tokens = $this->tokenizer->tokenize((string) $criteria->getTerm());
        $tokens = $this->tokenFilter->filter($tokens, $context);

        foreach ($tokens as $token) {
            $tokenBool = new BoolQuery();
            $bool->add($tokenBool, $isAndSearch ? BoolQuery::MUST : BoolQuery::SHOULD);

            foreach ($searchConfig as $item) {
                $ranking = $item['ranking'];

                if (!str_contains($item['field'], 'customFields')) {
                    $searchField = $item['field'] . '.search';
                    $ngramField = $item['field'] . '.ngram';
                } else {
                    $searchField = $item['field'];
                    $ngramField = $item['field'];
                }

                $queries = [];

                $queries[] = new MatchQuery($searchField, $token, ['boost' => 5 * $ranking]);
                $queries[] = new MatchPhrasePrefixQuery($searchField, $token, ['boost' => $ranking, 'slop' => 5]);
                $queries[] = new WildcardQuery($searchField, '*' . $token . '*');

                if ($item['tokenize']) {
                    $queries[] = new MatchQuery($searchField, $token, ['fuzziness' => 'auto', 'boost' => 3 * $ranking]);
                    $queries[] = new MatchQuery($ngramField, $token);
                }

                if (str_contains($item['field'], '.') && !str_contains($item['field'], 'customFields')) {
                    $nested = strtok($item['field'], '.');

                    foreach ($queries as $query) {
                        $tokenBool->add(new NestedQuery($nested, $query), BoolQuery::SHOULD);
                    }

                    continue;
                }

                foreach ($queries as $query) {
                    $tokenBool->add($query, BoolQuery::SHOULD);
                }
            }
        }

        return $bool;
    }

    public function getDecorated(): AbstractProductSearchQueryBuilder
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @return array<SearchConfig>
     */
    private function fetchConfig(Context $context): array
    {
        /** @var array<SearchConfig> $config */
        $config = $this->connection->fetchAllAssociative(
            'SELECT
product_search_config.and_logic,
product_search_config_field.field,
product_search_config_field.tokenize,
product_search_config_field.ranking

FROM product_search_config
INNER JOIN product_search_config_field ON(product_search_config_field.product_search_config_id = product_search_config.id)
WHERE product_search_config.language_id = :languageId AND product_search_config_field.searchable = 1 AND product_search_config_field.field NOT IN(:fields)',
            [
                'languageId' => Uuid::fromHexToBytes($context->getLanguageId()),
                'fields' => self::NOT_SUPPORTED_FIELDS,
            ],
            [
                'fields' => ArrayParameterType::STRING,
            ]
        );

        return $config;
    }
}
