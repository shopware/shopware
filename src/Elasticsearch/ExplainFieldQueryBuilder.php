<?php declare(strict_types=1);

namespace Shopware\Elasticsearch;

use OpenSearchDSL\BuilderInterface;
use OpenSearchDSL\Query\Compound\DisMaxQuery;
use OpenSearchDSL\Query\Joining\NestedQuery;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Product\SearchFieldConfig;

/**
 * @internal
 */
#[Package('inventory')]
class ExplainFieldQueryBuilder extends AbstractFieldQueryBuilder
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractFieldQueryBuilder $fieldQueryBuilder,
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
        $query = $this->getDecorated()->build($field, $token, $config, $context);

        if (!$query || !$context->hasState(Context::ELASTICSEARCH_EXPLAIN_MODE) || !method_exists($query, 'addParameter')) {
            return $query;
        }

        // Text fields produce a DisMax whose individual clauses are already
        // named with their match type by FieldQueryBuilder — don't add a
        // second, type-less name on top of it.
        if ($query instanceof DisMaxQuery) {
            return $query;
        }

        // Like the clause names of FieldQueryBuilder, the field-level score already carries the field ranking.
        $explainPayload = json_encode([
            'field' => $config->getField(),
            'term' => $token,
            'ranking' => $config->getRanking(),
            'type' => $config->isPhrase() ? 'phrase' : 'exact',
            'weighted' => true,
        ]);

        if ($query instanceof NestedQuery) {
            $query->addParameter('inner_hits', [
                '_source' => false,
                'explain' => true,
                'name' => $explainPayload,
            ]);
        }

        $query->addParameter('_name', $explainPayload);

        return $query;
    }
}
