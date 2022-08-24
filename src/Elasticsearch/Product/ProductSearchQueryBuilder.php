<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use Doctrine\DBAL\Connection;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\FullText\MatchPhrasePrefixQuery;
use ONGR\ElasticsearchDSL\Query\FullText\MatchQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\WildcardQuery;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Filter\AbstractTokenFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Tokenizer;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @phpstan-type SearchConfig array{and_logic: string, field: string, tokenize: int, ranking: int}
 */
class ProductSearchQueryBuilder extends AbstractProductSearchQueryBuilder
{
    private Connection $connection;

    /**
     * @var array<string, array<SearchConfig>>
     */
    private array $config = [];

    private AbstractTokenFilter $tokenFilter;

    private Tokenizer $tokenizer;

    /**
     * @internal
     */
    public function __construct(Connection $connection, Tokenizer $tokenizer, AbstractTokenFilter $tokenFilter)
    {
        $this->connection = $connection;
        $this->tokenFilter = $tokenFilter;
        $this->tokenizer = $tokenizer;
    }

    public function buildQuery(Criteria $criteria, Context $context): BoolQuery
    {
        $bool = new BoolQuery();

        $searchConfig = $this->fetchConfig($context);

        $isAndSearch = $searchConfig[0]['and_logic'] === '1';

        $tokens = $this->tokenizer->tokenize((string) $criteria->getTerm());
        $tokens = $this->tokenFilter->filter($tokens, $context);

        foreach ($tokens as $token) {
            $tokenBool = new BoolQuery();

            foreach ($searchConfig as $item) {
                $ranking = $item['ranking'];
                $searchField = $item['field'] . '.search';
                $ngramField = $item['field'] . '.ngram';

                $tokenBool->add(
                    new MatchQuery($searchField, $token, ['boost' => 5 * $ranking]),
                    BoolQuery::SHOULD
                );

                $tokenBool->add(
                    new MatchPhrasePrefixQuery($searchField, $token, ['boost' => $ranking, 'slop' => 5]),
                    BoolQuery::SHOULD
                );

                $tokenBool->add(
                    new WildcardQuery($searchField, '*' . $token . '*'),
                    BoolQuery::SHOULD
                );

                if ($item['tokenize']) {
                    $tokenBool->add(
                        new MatchQuery($searchField, $token, ['fuzziness' => 'auto', 'boost' => 3 * $ranking]),
                        BoolQuery::SHOULD
                    );
                    $tokenBool->add(
                        new MatchQuery($ngramField, $token),
                        BoolQuery::SHOULD
                    );
                }
            }

            $bool->add($tokenBool, $isAndSearch ? BoolQuery::MUST : BoolQuery::SHOULD);
        }

        return $bool;
    }

    public function getDecorated(): AbstractProductSearchQueryBuilder
    {
        throw new DecorationPatternException(self::class);
    }

    public function reset(): void
    {
        $this->config = [];
    }

    /**
     * @return array<SearchConfig>
     */
    private function fetchConfig(Context $context): array
    {
        if (isset($this->config[$context->getLanguageId()])) {
            return $this->config[$context->getLanguageId()];
        }

        /** @var array<SearchConfig> $config */
        $config = $this->connection->fetchAllAssociative('SELECT
product_search_config.and_logic,
product_search_config_field.field,
product_search_config_field.tokenize,
product_search_config_field.ranking


FROM product_search_config
INNER JOIN product_search_config_field ON(product_search_config_field.product_search_config_id = product_search_config.id)
WHERE product_search_config.language_id = ? AND product_search_config_field.searchable = 1 AND product_search_config_field.field = "name"', [
            Uuid::fromHexToBytes($context->getLanguageId()),
        ]);

        $this->config[$context->getLanguageId()] = $config;

        return $this->config[$context->getLanguageId()];
    }
}
