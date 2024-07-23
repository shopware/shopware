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
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Filter\AbstractTokenFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\TokenizerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Elasticsearch\ElasticsearchException;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;

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
        private readonly EntityDefinition $productDefinition,
        private readonly AbstractTokenFilter $tokenFilter,
        private readonly TokenizerInterface $tokenizer,
        private readonly ElasticsearchHelper $elasticsearchHelper
    ) {
    }

    public function build(Criteria $criteria, Context $context): BoolQuery
    {
        $originalTerm = mb_strtolower((string) $criteria->getTerm());

        $bool = new BoolQuery();

        $searchConfig = $this->fetchConfig($context);

        $isAndSearch = $searchConfig[0]['and_logic'] === '1';

        $tokens = $this->tokenizer->tokenize((string) $criteria->getTerm());
        $tokens = $this->tokenFilter->filter($tokens, $context);

        if (!\in_array($originalTerm, $tokens, true)) {
            $tokens[] = $originalTerm;
        }

        if (empty(array_filter($tokens))) {
            throw ElasticsearchException::emptyQuery();
        }

        foreach ($tokens as $token) {
            $tokenBool = new BoolQuery();

            foreach ($searchConfig as $item) {
                if ($this->elasticsearchHelper->enabledMultilingualIndex()) {
                    $config = new SearchFieldConfig((string) $item['field'], (int) $item['ranking'], (bool) $item['tokenize']);
                    $field = $this->helper->getField($config->getField(), $this->productDefinition, $this->productDefinition->getEntityName(), false);
                    $association = $this->helper->getAssociationPath($config->getField(), $this->productDefinition);
                    $root = $association ? explode('.', $association)[0] : null;

                    if ($field instanceof TranslatedField) {
                        $this->buildTranslatedFieldTokenQueries($tokenBool, $token, $config, $context, $root);

                        continue;
                    }

                    $this->buildTokenQuery($tokenBool, $token, $config, $root);

                    continue;
                }

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

            $bool->add($tokenBool, $isAndSearch ? BoolQuery::MUST : BoolQuery::SHOULD);
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
        foreach ($context->getLanguageIdChain() as $languageId) {
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
                    'languageId' => Uuid::fromHexToBytes($languageId),
                    'excludedFields' => self::NOT_SUPPORTED_FIELDS,
                ],
                [
                    'excludedFields' => ArrayParameterType::STRING,
                ]
            );

            if (!empty($config)) {
                return $config;
            }
        }

        throw ElasticsearchProductException::configNotFound();
    }

    private function buildTokenQuery(BoolQuery $tokenBool, string $token, SearchFieldConfig $config, ?string $root = null): void
    {
        $queries = [];

        $searchField = $config->isCustomField() ? $config->getField() : $config->getField() . '.search';

        $queries[] = new MatchQuery($searchField, $token, ['boost' => 5 * $config->getRanking()]);
        $queries[] = new MatchPhrasePrefixQuery($searchField, $token, ['boost' => $config->getRanking(), 'slop' => 5]);
        $queries[] = new WildcardQuery($searchField, '*' . $token . '*');

        if ($config->tokenize()) {
            $ngramField = $config->isCustomField() ? $config->getField() : $config->getField() . '.ngram';
            $queries[] = new MatchQuery($searchField, $token, ['fuzziness' => 'auto', 'boost' => 3 * $config->getRanking()]);
            $queries[] = new MatchQuery($ngramField, $token);
        }

        foreach ($queries as $query) {
            if ($root) {
                $query = new NestedQuery($root, $query);
            }

            $tokenBool->add($query, BoolQuery::SHOULD);
        }
    }

    private function buildTranslatedFieldTokenQueries(BoolQuery $tokenBool, string $token, SearchFieldConfig $config, Context $context, ?string $root = null): void
    {
        $multiMatchFields = [];
        $fuzzyMatchFields = [];
        $matchPhraseFields = [];
        $ngramFields = [];

        foreach ($context->getLanguageIdChain() as $languageId) {
            $searchField = $this->buildTranslatedFieldName($config, $languageId, 'search');

            $multiMatchFields[] = $searchField;
            $matchPhraseFields[] = $searchField;

            if ($config->tokenize()) {
                $ngramField = $this->buildTranslatedFieldName($config, $languageId, 'ngram');
                $fuzzyMatchFields[] = $searchField;
                $ngramFields[] = $ngramField;
            }
        }

        $queries = [
            new MultiMatchQuery($multiMatchFields, $token, [
                'type' => 'best_fields',
                'fuzziness' => 0,
                'boost' => $config->getRanking() * 5,
            ]),
            new MultiMatchQuery($matchPhraseFields, $token, [
                'type' => 'phrase_prefix',
                'slop' => 5,
                'boost' => $config->getRanking(),
            ]),
        ];

        if ($config->tokenize()) {
            $queries[] = new MultiMatchQuery($fuzzyMatchFields, $token, [
                'type' => 'best_fields',
                'fuzziness' => 'auto',
                'boost' => $config->getRanking() * 3,
            ]);

            $queries[] = new MultiMatchQuery($ngramFields, $token, [
                'type' => 'phrase',
                'boost' => $config->getRanking(),
            ]);
        }

        foreach ($queries as $query) {
            if ($root) {
                $query = new NestedQuery($root, $query);
            }

            $tokenBool->add($query, BoolQuery::SHOULD);
        }
    }

    private function buildTranslatedFieldName(SearchFieldConfig $fieldConfig, string $languageId, string $suffix = ''): string
    {
        if ($fieldConfig->isCustomField()) {
            $parts = explode('.', $fieldConfig->getField());

            return sprintf('%s.%s.%s', $parts[0], $languageId, $parts[1]);
        }

        return sprintf('%s.%s.%s', $fieldConfig->getField(), $languageId, $suffix);
    }
}
