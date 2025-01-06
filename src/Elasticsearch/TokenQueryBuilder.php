<?php declare(strict_types=1);

namespace Shopware\Elasticsearch;

use OpenSearchDSL\BuilderInterface;
use OpenSearchDSL\Query\Compound\BoolQuery;
use OpenSearchDSL\Query\Compound\DisMaxQuery;
use OpenSearchDSL\Query\FullText\MatchPhrasePrefixQuery;
use OpenSearchDSL\Query\FullText\MatchQuery;
use OpenSearchDSL\Query\Joining\NestedQuery;
use OpenSearchDSL\Query\TermLevel\TermQuery;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomField\CustomFieldService;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchEntitySearcher;
use Shopware\Elasticsearch\Product\SearchFieldConfig;

/**
 * @phpstan-type SearchConfig array{and_logic: string, field: string, tokenize: int, ranking: float|int}
 *
 * @deprecated tag:v6.7.0 - reason:becomes-final
 */
#[Package('core')]
class TokenQueryBuilder
{
    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        private readonly CustomFieldService $customFieldService
    ) {
    }

    /**
     * @param SearchFieldConfig[] $configs
     * @param Context|string[] $context
     *
     * @deprecated tag:v6.7.0 - $context will be typed as Context only
     */
    public function build(string $entity, string $token, array $configs, array|Context $context): ?BuilderInterface
    {
        $explainMode = false;

        if (\is_array($context)) {
            Feature::triggerDeprecationOrThrow('v6.7.0.0', 'The $context is now a Context object');
        }

        if ($context instanceof Context) {
            $languageIdChain = $context->getLanguageIdChain();
            $explainMode = $context->hasState(ElasticsearchEntitySearcher::EXPLAIN_MODE);
        } else {
            $languageIdChain = $context;
        }

        $tokenQueries = [];

        $definition = $this->definitionRegistry->getByEntityName($entity);

        foreach ($configs as $config) {
            $field = EntityDefinitionQueryHelper::getField($config->getField(), $definition, $definition->getEntityName(), false);
            $fieldDefinition = EntityDefinitionQueryHelper::getAssociatedDefinition($definition, $config->getField());
            $real = $field instanceof TranslatedField ? EntityDefinitionQueryHelper::getTranslatedField($fieldDefinition, $field) : $field;

            if (str_contains($config->getField(), 'customFields')) {
                $real = $this->customFieldService->getCustomField(str_replace('customFields.', '', $config->getField()));
            }

            if (!$real) {
                continue;
            }

            $root = EntityDefinitionQueryHelper::getRoot($config->getField(), $definition);

            $fieldQuery = $field instanceof TranslatedField ?
                $this->translatedQuery($real, $token, $config, $languageIdChain) :
                $this->matchQuery($real, $token, $config);

            if (!$fieldQuery) {
                continue;
            }

            if ($root !== null) {
                $fieldQuery = new NestedQuery($root, $fieldQuery);
            }

            if ($explainMode) {
                $fieldQuery = $this->explainQuery($token, $fieldQuery, $config);
            }

            $tokenQueries[] = $fieldQuery;
        }

        if (empty($tokenQueries)) {
            return null;
        }

        if (\count($tokenQueries) === 1) {
            return $tokenQueries[0];
        }

        return new BoolQuery([BoolQuery::SHOULD => $tokenQueries]);
    }

    private function matchQuery(Field $field, string $token, SearchFieldConfig $config): ?BuilderInterface
    {
        if ($field instanceof StringField || $field instanceof LongTextField || $field instanceof ListField) {
            $queries = [];

            $searchField = $config->getField() . '.search';
            $operator = $config->isAndLogic() ? 'and' : 'or';

            $tokenCount = \count(\explode(' ', $token));

            $queries[] = new MatchQuery($searchField, $token, [
                'boost' => $config->getRanking(),
                'fuzziness' => $config->tokenize() ? 'auto' : 1,
                'operator' => $operator,
            ]);

            // Prefix match
            $queries[] = new MatchPhrasePrefixQuery($searchField, $token, [
                'boost' => 0.6 * $config->getRanking(),
                'slop' => 3,
                'max_expansions' => 10,
            ]);

            if ($config->tokenize() && $tokenCount === 1) {
                // ngram search
                $queries[] = new MatchQuery($config->getField() . '.ngram', $token, [
                    'boost' => 0.4 * $config->getRanking(),
                ]);
            }

            $dismax = new DisMaxQuery();

            foreach ($queries as $query) {
                $dismax->addQuery($query);
            }

            return $dismax;
        }

        if ($field instanceof IntField || $field instanceof FloatField || $field instanceof PriceField) {
            if (!\is_numeric($token)) {
                return null;
            }

            $token = $field instanceof IntField ? (int) $token : (float) $token;
        }

        return new TermQuery($config->getField(), $token, ['boost' => $config->getRanking()]);
    }

    /**
     * @param string[] $languageIdChain
     */
    private function translatedQuery(Field $field, string $token, SearchFieldConfig $config, array $languageIdChain): ?BuilderInterface
    {
        $languageQueries = [];

        $ranking = $config->getRanking();

        foreach ($languageIdChain as $languageId) {
            $searchField = $this->buildTranslatedFieldName($config, $languageId);

            $languageConfig = new SearchFieldConfig(
                $searchField,
                $ranking,
                $config->tokenize(),
                $config->isAndLogic(),
            );

            $languageQuery = $this->matchQuery($field, $token, $languageConfig);

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

    private function buildTranslatedFieldName(SearchFieldConfig $fieldConfig, string $languageId): string
    {
        if ($fieldConfig->isCustomField()) {
            $parts = explode('.', $fieldConfig->getField());

            return \sprintf('%s.%s.%s', $parts[0], $languageId, $parts[1]);
        }

        return \sprintf('%s.%s', $fieldConfig->getField(), $languageId);
    }

    private function explainQuery(string $token, BuilderInterface $fieldQuery, SearchFieldConfig $config): BuilderInterface
    {
        $explainPayload = json_encode([
            'field' => $config->getField(),
            'term' => $token,
            'ranking' => $config->getRanking(),
        ]);

        if (!method_exists($fieldQuery, 'addParameter')) {
            return $fieldQuery;
        }

        if ($fieldQuery instanceof NestedQuery) {
            $fieldQuery->addParameter('inner_hits', [
                '_source' => false,
                'explain' => true,
                'name' => $explainPayload,
            ]);
        }

        $fieldQuery->addParameter('_name', $explainPayload);

        return $fieldQuery;
    }
}
