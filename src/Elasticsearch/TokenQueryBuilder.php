<?php declare(strict_types=1);

namespace Shopware\Elasticsearch;

use OpenSearchDSL\BuilderInterface;
use OpenSearchDSL\Query\Compound\BoolQuery;
use OpenSearchDSL\Query\Compound\DisMaxQuery;
use OpenSearchDSL\Query\FullText\MatchPhrasePrefixQuery;
use OpenSearchDSL\Query\FullText\MatchQuery;
use OpenSearchDSL\Query\Joining\NestedQuery;
use OpenSearchDSL\Query\TermLevel\TermQuery;
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
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomField\CustomFieldService;
use Shopware\Elasticsearch\Product\SearchFieldConfig;

/**
 * @phpstan-type SearchConfig array{and_logic: string, field: string, tokenize: int, ranking: float|int}
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
     * @param string[] $languageIdChain
     */
    public function build(string $entity, string $token, array $configs, array $languageIdChain): ?BuilderInterface
    {
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
                self::translatedQuery($real, $token, $config, $languageIdChain) :
                self::matchQuery($real, $token, $config);

            if (!$fieldQuery) {
                continue;
            }

            if ($root !== null) {
                $fieldQuery = new NestedQuery($root, $fieldQuery);
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

    private static function matchQuery(Field $field, string $token, SearchFieldConfig $config): ?BuilderInterface
    {
        if ($field instanceof StringField || $field instanceof LongTextField || $field instanceof ListField) {
            $queries = [];

            $searchField = $config->getField() . '.search';
            $ngramField = $config->getField() . '.ngram';

            // Exact match
            $queries[] = new MatchQuery($searchField, $token, ['boost' => 5 * $config->getRanking(), 'fuzziness' => 0]);
            // Prefix match
            $queries[] = new MatchPhrasePrefixQuery($searchField, $token, ['boost' => 4 * $config->getRanking(), 'slop' => 3, 'max_expansions' => 10]);

            if ($config->tokenize()) {
                // fuzziness auto
                $queries[] = new MatchQuery($searchField, $token, ['boost' => 3 * $config->getRanking(), 'fuzziness' => 'auto']);
                // ngram search
                $queries[] = new MatchQuery($ngramField, $token, ['boost' => 2 * $config->getRanking()]);
            } else {
                // allow low fuzziness for typo correction
                $queries[] = new MatchQuery($searchField, $token, ['boost' => 3 * $config->getRanking(), 'fuzziness' => 1]);
            }

            return new BoolQuery([BoolQuery::SHOULD => $queries]);
        }

        if ($field instanceof IntField || $field instanceof FloatField || $field instanceof PriceField) {
            if (!\is_numeric($token)) {
                return null;
            }

            $token = $field instanceof IntField ? (int) $token : (float) $token;
        }

        return new TermQuery($config->getField(), $token, ['boost' => 5 * $config->getRanking()]);
    }

    /**
     * @param string[] $languageIdChain
     */
    private static function translatedQuery(Field $field, string $token, SearchFieldConfig $config, array $languageIdChain): ?BuilderInterface
    {
        $languageQueries = [];

        $ranking = $config->getRanking();

        foreach ($languageIdChain as $languageId) {
            $searchField = self::buildTranslatedFieldName($config, $languageId);

            $languageConfig = new SearchFieldConfig(
                $searchField,
                $ranking,
                $config->tokenize(),
                $config->isAndLogic(),
            );

            $languageQuery = self::matchQuery($field, $token, $languageConfig);

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

    private static function buildTranslatedFieldName(SearchFieldConfig $fieldConfig, string $languageId): string
    {
        if ($fieldConfig->isCustomField()) {
            $parts = explode('.', $fieldConfig->getField());

            return sprintf('%s.%s.%s', $parts[0], $languageId, $parts[1]);
        }

        return sprintf('%s.%s', $fieldConfig->getField(), $languageId);
    }
}
