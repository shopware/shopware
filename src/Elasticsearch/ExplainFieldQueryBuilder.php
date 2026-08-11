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

        // A DisMax's clauses are already named with their real match type by
        // FieldQueryBuilder, as is any other clause that carries a name — keep those.
        if ($query instanceof DisMaxQuery) {
            return $query;
        }

        if ($query->hasParameter('_name')) {
            return $query;
        }

        // Field-level name; its score already carries the field weight (the query's
        // boost is the ranking), hence `weighted`.
        $payload = [
            'field' => $config->getField(),
            'term' => $token,
            'ranking' => $config->getRanking(),
        ];

        // No match type: the clauses inside a nested query decide how it matched and
        // ES doesn't surface their names at hit level. Only the phrase path is certain.
        if ($config->isPhrase()) {
            $payload['type'] = 'phrase';
        }

        $payload['weighted'] = true;

        $explainPayload = json_encode($payload);

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
