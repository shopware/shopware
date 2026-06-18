<?php declare(strict_types=1);

namespace Shopware\Elasticsearch;

use OpenSearchDSL\BuilderInterface;
use OpenSearchDSL\Query\Compound\DisMaxQuery;
use Shopware\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Product\ElasticsearchOptimizeSwitch;
use Shopware\Elasticsearch\Product\SearchFieldConfig;

/**
 * @internal
 */
#[Package('inventory')]
class TranslatedFieldQueryBuilder extends AbstractFieldQueryBuilder
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractFieldQueryBuilder $fieldQueryBuilder,
        private readonly AbstractKeyValueStorage $storage,
        private readonly float $dismaxTieBreaker = 0.2,
    ) {
    }

    public function getDecorated(): AbstractFieldQueryBuilder
    {
        return $this->fieldQueryBuilder;
    }

    public function build(
        ResolvedField $field,
        string $token,
        SearchFieldConfig $config,
        Context $context,
    ): ?BuilderInterface {
        if (!$field instanceof TranslatedResolvedField) {
            return $this->getDecorated()->build($field, $token, $config, $context);
        }

        $languageIdChain = $this->getLanguageIdChain($field->getTranslatedField(), $context);
        $languageQueries = [];
        $ranking = $config->getRanking();

        foreach ($languageIdChain as $languageId) {
            $searchField = $this->buildTranslatedFieldName($config, $languageId);

            $languageConfig = new SearchFieldConfig(
                $searchField,
                $ranking,
                $config->tokenize(),
                $config->isAndLogic(),
                $config->usePrefixMatch(),
                $config->useExactSubfield(),
            );

            $languageQuery = $this->getDecorated()->build(
                new ResolvedField($field->getResolvedField()),
                $token,
                $languageConfig,
                $context,
            );

            $ranking *= 0.8;

            if (!$languageQuery) {
                continue;
            }

            $languageQueries[] = $languageQuery;
        }

        if ($languageQueries === []) {
            return null;
        }

        $fieldQuery = \count($languageQueries) === 1
            ? $languageQueries[0]
            : $this->buildDisMaxQuery($languageQueries);

        return $fieldQuery;
    }

    /**
     * @return list<string>
     */
    private function getLanguageIdChain(TranslatedField $field, Context $context): array
    {
        if ($this->isSortableTranslatedField($field)) {
            return [$context->getLanguageId()];
        }

        return $context->getLanguageIdChain();
    }

    private function isSortableTranslatedField(TranslatedField $field): bool
    {
        return $field->useForSorting() && (Feature::isActive('v6.8.0.0') || $this->storage->has(ElasticsearchOptimizeSwitch::FLAG));
    }

    private function buildTranslatedFieldName(SearchFieldConfig $fieldConfig, string $languageId): string
    {
        if ($fieldConfig->isCustomField()) {
            $parts = explode('.', $fieldConfig->getField());

            return \sprintf('%s.%s.%s', $parts[0], $languageId, $parts[1]);
        }

        return \sprintf('%s.%s', $fieldConfig->getField(), $languageId);
    }

    /**
     * @param list<BuilderInterface> $queries
     */
    private function buildDisMaxQuery(array $queries): DisMaxQuery
    {
        $dismax = new DisMaxQuery();

        foreach ($queries as $query) {
            $dismax->addQuery($query);
        }

        $dismax->addParameter('tie_breaker', $this->dismaxTieBreaker);

        return $dismax;
    }
}
