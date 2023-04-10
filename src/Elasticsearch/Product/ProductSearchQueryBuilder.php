<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use OpenSearchDSL\Query\Compound\BoolQuery;
use OpenSearchDSL\Query\FullText\MatchPhrasePrefixQuery;
use OpenSearchDSL\Query\FullText\MatchQuery;
use OpenSearchDSL\Query\FullText\MultiMatchQuery;
use OpenSearchDSL\Query\Joining\NestedQuery;
use OpenSearchDSL\Query\TermLevel\WildcardQuery;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
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
        private readonly EntityDefinitionQueryHelper $helper,
        private readonly ProductDefinition $productDefinition,
        private readonly AbstractTokenFilter $tokenFilter,
        private readonly Tokenizer $tokenizer
    ) {
    }

    public function build(Criteria $criteria, Context $context): BoolQuery
    {
        $bool = new BoolQuery();

        $searchConfig = $this->fetchConfig($context);

        $isAndSearch = $searchConfig[0]['and_logic'] === '1';

        $languageIds = $context->getLanguageIdChain();

        $tokens = $this->tokenizer->tokenize((string) $criteria->getTerm());
        $tokens = $this->tokenFilter->filter($tokens, $context);

        foreach ($tokens as $token) {
            $bool->add($this->buildTokenQuery($token, $searchConfig, $languageIds), $isAndSearch ? BoolQuery::MUST : BoolQuery::SHOULD);
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
WHERE product_search_config.language_id = :languageId AND product_search_config_field.searchable = 1 AND product_search_config_field.field NOT IN(:excludedFields)',
            [
                'languageId' => Uuid::fromHexToBytes($context->getLanguageId()),
                'excludedFields' => self::NOT_SUPPORTED_FIELDS,
            ],
            [
                'excludedFields' => ArrayParameterType::STRING,
            ]
        );

        return $config;
    }

    private function buildTokenQuery(string $token, array $searchConfig, array $languageIds): BoolQuery
    {
        $tokenBool = new BoolQuery();

        foreach ($searchConfig as $item) {
            $multiMatchFields = [];
            $queries = [];

            $ranking = $item['ranking'];
            $tokenize = (bool) $item['tokenize'];
            $field = $this->helper->getField($item['field'], $this->productDefinition, $this->productDefinition->getEntityName(), false);
            $isCustomField = str_contains($item['field'], 'customFields');
            $fieldName = $item['field'];

            foreach ($languageIds as $languageId) {
                if ($field instanceof TranslatedField) {
                    $fieldName = sprintf('%s_%s', $item['field'], $languageId);
                }

                $searchField = $isCustomField ? $fieldName : $fieldName . '.search';
                $ngramField = $isCustomField ? $fieldName : $fieldName . '.ngram';

                $multiMatchFields[] = $searchField;

                $queries[] = new MatchPhrasePrefixQuery($searchField, $token, ['boost' => $ranking, 'slop' => 5]);
                $queries[] = new WildcardQuery($searchField, '*' . $token . '*');

                if ($tokenize) {
                    $queries[] = new MatchQuery($searchField, $token, ['fuzziness' => 'auto', 'boost' => 3 * $ranking]);
                    $queries[] = new MatchQuery($ngramField, $token);
                }

                // Non-translated field should only be added once
                if (!$field instanceof TranslatedField) {
                    break;
                }
            }

            $queries[] = new MultiMatchQuery($multiMatchFields, $token, [
                'type' => 'best_fields',
                'fuzziness' => 0,
                'boost' => $ranking * 5,
            ]);

            $association = $this->helper::getAssociationPath($item['field'], $this->productDefinition);
            $root = $association ? explode('.', $association)[0] : null;

            foreach ($queries as $query) {
                if ($root) {
                    $query = new NestedQuery($root, $query);
                }

                $tokenBool->add($query, BoolQuery::SHOULD);
            }
        }

        return $tokenBool;
    }
}
