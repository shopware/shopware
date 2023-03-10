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
        private readonly ProductDefinition $productDefinition
    ) {
    }

    public function build(Criteria $criteria, Context $context): BoolQuery
    {
        $bool = new BoolQuery();

        $searchConfig = $this->fetchConfig($context);

        $isAndSearch = $searchConfig[0]['and_logic'] === '1';

        $languageIds = $context->getLanguageIdChain();

        $tokenBool = new BoolQuery();

        $bool->add($tokenBool, BoolQuery::SHOULD);

        foreach ($searchConfig as $item) {
            $ranking = $item['ranking'];

            $fieldName = $item['field'];
            $suffix = $item['tokenize'] ? '.ngram' : '.search';

            $association = null;

            if (str_contains($item['field'], '.')) {
                $parts = explode('.', $item['field']);
                $association = $parts[0] !== 'customFields' ? $parts[0] : null;
                $fieldName = end($parts);
            }

            $field = $this->helper->getField($item['field'], $this->productDefinition, $this->productDefinition->getEntityName(), false);

            if ($field instanceof TranslatedField) {
                $multiMatchFields = [];

                foreach ($languageIds as $languageId) {
                    $fieldName = $association ? $association . '_' . $languageId . '.' . $fieldName : $fieldName . '_' . $languageId;

                    $searchField = $fieldName;

                    if (!str_contains($fieldName, 'customFields')) {
                        $searchField = $fieldName . $suffix;
                    }

                    if ($languageId === $context->getLanguageId()) {
                        $multiMatchFields[] = $searchField . '^2';
                    } else {
                        $multiMatchFields[] = $searchField;
                    }
                }

                if ($association) {
                    $tokenBool->add(new NestedQuery($association . '_' . $languageId, new MultiMatchQuery($multiMatchFields, $criteria->getTerm(), [
                        'type' => 'best_fields',
                        'fuzziness' => 'auto',
                        'operator' => $isAndSearch ? 'and' : 'or',
                        'boost' => $ranking * 5,
                    ])), BoolQuery::SHOULD);
                } else {
                    $tokenBool->add(new MultiMatchQuery($multiMatchFields, $criteria->getTerm(), [
                        'type' => 'best_fields',
                        'fuzziness' => 'auto',
                        'operator' => $isAndSearch ? 'and' : 'or',
                        'boost' => $ranking * 5,
                    ]), BoolQuery::SHOULD);
                }
            } else {
                $queries = [];

                $queries[] = new MatchQuery($fieldName . '.search', $criteria->getTerm(), ['boost' => 5 * $ranking]);
                $queries[] = new MatchPhrasePrefixQuery($fieldName . '.search', $criteria->getTerm(), ['boost' => $ranking, 'slop' => 5]);
                $queries[] = new WildcardQuery($fieldName . '.search', '*' . $criteria->getTerm() . '*');

                if ($item['tokenize']) {
                    $queries[] = new MatchQuery($fieldName . '.ngram', $criteria->getTerm(), ['fuzziness' => 'auto', 'boost' => 3 * $ranking]);
                    $queries[] = new MatchQuery($fieldName . '.ngram', $criteria->getTerm());
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
