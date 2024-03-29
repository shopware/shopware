<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Product;

use OpenSearchDSL\BuilderInterface;
use OpenSearchDSL\Query\Compound\BoolQuery;
use OpenSearchDSL\Query\Compound\DisMaxQuery;
use OpenSearchDSL\Query\FullText\MatchPhrasePrefixQuery;
use OpenSearchDSL\Query\FullText\MatchQuery;
use OpenSearchDSL\Query\Joining\NestedQuery;
use OpenSearchDSL\Query\TermLevel\TermQuery;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Filter\AbstractTokenFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\TokenizerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\CustomField\CustomFieldService;
use Shopware\Elasticsearch\ElasticsearchException;

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
        private readonly EntityDefinitionQueryHelper $helper,
        private readonly EntityDefinition $productDefinition,
        private readonly AbstractTokenFilter $tokenFilter,
        private readonly TokenizerInterface $tokenizer,
        private readonly CustomFieldService $customFieldService,
        private readonly SearchConfigLoader $configLoader
    ) {
    }

    public function getDecorated(): AbstractProductSearchQueryBuilder
    {
        throw new DecorationPatternException(self::class);
    }

    public function build(Criteria $criteria, Context $context): BoolQuery
    {
        $originalTerm = mb_strtolower((string) $criteria->getTerm());

        /** @phpstan-ignore-next-line - v6.7.0.0 - 2nd parameter will be added in Tokenizer::tokenize */
        $tokens = $this->tokenizer->tokenize($originalTerm, ['/', '\\']);
        $tokens = $this->tokenFilter->filter($tokens, $context);

        if (!\in_array($originalTerm, $tokens, true)) {
            $tokens[] = $originalTerm;
        }

        $configs = $this->getConfigs($context);
        $tokensQueries = $this->tokensQueries($tokens, $configs, $context);

        if (empty($configs) || empty($tokensQueries)) {
            throw ElasticsearchException::emptyQuery();
        }

        if (\count($tokensQueries) === 1) {
            return $tokensQueries[0];
        }

        $andSearch = $configs[0]->isAndLogic() ? BoolQuery::MUST : BoolQuery::SHOULD;

        return new BoolQuery([
            $andSearch => $tokensQueries,
        ]);
    }

    /**
     * @param list<string> $tokens
     * @param list<SearchFieldConfig> $configs
     *
     * @return list<BoolQuery>
     */
    private function tokensQueries(array $tokens, array $configs, Context $context): array
    {
        $tokensQueries = [];

        foreach ($configs as $config) {
            if ($config->getFieldDefinition() === null) {
                continue;
            }

            $association = $this->helper->getAssociationPath($config->getField(), $this->productDefinition);
            $root = $association ? explode('.', $association)[0] : null;

            foreach ($tokens as $token) {
                $fieldQuery = $config->isTranslatedField() ?
                    self::translatedQuery($token, $config, $context) :
                    self::matchQuery($token, $config);

                if (!$fieldQuery) {
                    continue;
                }

                if ($root) {
                    $fieldQuery = new NestedQuery($root, $fieldQuery);
                }

                $tokensQueries[$token] = $tokensQueries[$token] ?? [];
                $tokensQueries[$token][] = $fieldQuery;
            }
        }

        foreach ($tokensQueries as $token => $queries) {
            if (\count($queries) === 1) {
                $tokensQueries[$token] = $queries[0];

                continue;
            }

            $tokensQueries[$token] = new BoolQuery([BoolQuery::SHOULD => $queries]);
        }

        return array_values($tokensQueries);
    }

    private function getRoot(string $field): ?string
    {
        $association = $this->helper->getAssociationPath($field, $this->productDefinition);

        return $association ? explode('.', $association)[0] : null;
    }

    private static function matchQuery(string $token, SearchFieldConfig $config): ?BuilderInterface
    {
        if ($config->isTextField()) {
            $queries = [];

            $searchField = $config->getField() . '.search';
            $ngramField = $config->getField() . '.ngram';

            $queries[] = new MatchQuery($searchField, $token, ['boost' => 5 * $config->getRanking(), 'fuzziness' => 0]);
            $queries[] = new MatchPhrasePrefixQuery($searchField, $token, ['boost' => $config->getRanking(), 'slop' => 3, 'max_expansions' => 10]);

            if ($config->tokenize()) {
                $queries[] = new MatchQuery($searchField, $token, ['boost' => 3 * $config->getRanking(), 'fuzziness' => 'auto']);
                $queries[] = new MatchQuery($ngramField, $token, ['boost' => $config->getRanking()]);
            }

            return new BoolQuery([BoolQuery::SHOULD => $queries]);
        }

        $field = $config->getFieldDefinition();

        if ($field instanceof IntField || $field instanceof FloatField) {
            if (!\is_numeric($token)) {
                return null;
            }

            $token = $field instanceof IntField ? (int) $token : (float) $token;
        }

        return new TermQuery($config->getField(), $token, ['boost' => 5 * $config->getRanking(), 'case_insensitive' => true]);
    }

    private static function translatedQuery(string $token, SearchFieldConfig $config, Context $context): ?BuilderInterface
    {
        $languageQueries = [];

        $ranking = $config->getRanking();

        foreach ($context->getLanguageIdChain() as $languageId) {
            $searchField = self::buildTranslatedFieldName($config, $languageId);

            $languageConfig = new SearchFieldConfig(
                $searchField,
                $ranking, // for each language we go "deeper" in the translation, we reduce the ranking by 20%
                $config->tokenize(),
                $config->isAndLogic(),
                $config->isTranslatedField(),
                $config->getFieldDefinition(),
            );

            $languageQuery = self::matchQuery($token, $languageConfig);

            $ranking = $config->getRanking() * 0.8; // for each language we go "deeper" in the translation, we reduce the ranking by 20%

            if (!$languageQuery) {
                continue;
            }

            $languageQueries[] = $languageQuery;
        }

        if (empty($languageQueries)) {
            return null;
        }

        if (\count($languageQueries) === 1) {
            return $languageQueries[0];
        }

        $dismax = new DisMaxQuery();

        foreach ($languageQueries as $languageQuery) {
            $dismax->addQuery($languageQuery);
        }

        return $dismax;
    }

    /**
     * @return SearchFieldConfig[]
     */
    private function getConfigs(Context $context): array
    {
        $searchConfig = $this->configLoader->load($context);

        $configs = [];

        foreach ($searchConfig as $item) {
            $fieldName = (string) $item['field'];
            $field = $this->helper->getField($fieldName, $this->productDefinition, $this->productDefinition->getEntityName(), false);
            $real = $field instanceof TranslatedField ? EntityDefinitionQueryHelper::getTranslatedField($this->productDefinition, $field) : $field;

            if (str_contains($fieldName, 'customFields')) {
                $real = $this->customFieldService->getCustomField(str_replace('customFields.', '', $fieldName));
            }

            if (!$real) {
                continue;
            }

            $configs[] = new SearchFieldConfig(
                $fieldName,
                (int) $item['ranking'],
                (bool) $item['tokenize'],
                (bool) $item['and_logic'],
                $field instanceof TranslatedField,
                $real,
            );
        }

        return $configs;
    }

    private static function buildTranslatedFieldName(SearchFieldConfig $fieldConfig, string $languageId): string
    {
        if ($fieldConfig->isCustomField()) {
            $parts = explode('.', $fieldConfig->getField());

            return sprintf('%s.%s.%s', $parts[0], $languageId, $parts[1]);
        }

        return sprintf('%s.%s', $fieldConfig->getField(), $languageId);
    }
}
