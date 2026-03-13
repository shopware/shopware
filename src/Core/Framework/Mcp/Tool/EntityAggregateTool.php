<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 *
 * Dedicated aggregation tool that loads zero entity rows and returns only
 * aggregation results, keeping the response well within the 100 KB limit.
 */
#[McpTool(name: 'shopware-entity-aggregate', description: 'Run aggregations (count, avg, sum, min, max, terms, date-histogram) over any Shopware entity without returning entity rows. Pass aggregation definitions as Admin API criteria JSON. Optional filters narrow the data set. Returns {success, data: {aggregations: {...}}}. Use shopware-entity-search to retrieve actual records.')]
#[Package('framework')]
class EntityAggregateTool
{
    use McpToolResponse;

    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $registry,
        private readonly RequestCriteriaBuilder $criteriaBuilder,
        private readonly McpContextProvider $contextProvider,
    ) {
    }

    public function __invoke(string $entity, string $aggregations, string $filters = '[]'): string
    {
        $context = $this->contextProvider->getContext();

        if ($error = $this->requirePrivilege($context, $entity . ':read')) {
            return $error;
        }

        $definition = $this->registry->getByEntityName($entity);
        $repository = $this->registry->getRepository($entity);

        $aggregationDefs = json_decode($aggregations, true, 512, \JSON_THROW_ON_ERROR);
        $filterDefs = json_decode($filters, true, 512, \JSON_THROW_ON_ERROR);

        if (!\is_array($aggregationDefs) || !\array_is_list($aggregationDefs)) {
            return $this->error('aggregations must be a JSON array of aggregation definitions.');
        }

        // Do not pass limit through the builder — RequestCriteriaBuilder rejects limit <= 0.
        // Set it directly on the Criteria object after parsing.
        $payload = ['aggregations' => $aggregationDefs];

        if (\is_array($filterDefs) && $filterDefs !== []) {
            $payload['filter'] = $filterDefs;
        }

        $criteriaObj = $this->criteriaBuilder->fromArray(
            $payload,
            new Criteria(),
            $definition,
            $context,
        );

        $criteriaObj->setLimit(0);

        $result = $repository->search($criteriaObj, $context);

        return $this->success([
            'aggregations' => $this->serializeAggregations($result->getAggregations()),
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function serializeAggregations(AggregationResultCollection $aggregations): array
    {
        $result = [];
        foreach ($aggregations as $name => $aggregation) {
            $data = json_decode(json_encode($aggregation, \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);
            unset($data['name'], $data['extensions'], $data['apiAlias']);
            $result[$name] = $data;
        }

        return $result;
    }
}
