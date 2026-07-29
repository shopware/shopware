<?php declare(strict_types=1);

namespace Shopware\Elasticsearch;

use OpenSearchDSL\BuilderInterface;
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

        $explainPayload = json_encode([
            'field' => $config->getField(),
            'term' => $token,
            'ranking' => $config->getRanking(),
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
