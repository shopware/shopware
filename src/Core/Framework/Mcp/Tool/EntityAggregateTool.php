<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Attribute\McpToolDependsOn;
use Shopware\Core\Framework\Mcp\Attribute\McpToolRequires;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 *
 * Dedicated aggregation tool that loads zero entity rows and returns only
 * aggregation results, keeping the response well within the 100 KB limit.
 */
#[McpTool(name: 'shopware-entity-aggregate', title: 'Entity Aggregate', description: 'The correct tool for count, sum, average, and other aggregate questions. Use this — not shopware-entity-search — for any \'how many\', \'total value\', or \'average\' query. Note: entity-search\'s _meta.total is pagination metadata, not a reporting count. Supports: count, avg, sum, min, max, terms, date-histogram. Returns only aggregation results, no entity rows. Pass aggregation definitions as Admin API criteria JSON.')]
#[McpToolDependsOn('shopware-entity-schema')]
#[McpToolRequires(entityParam: 'entity', operations: ['read'])]
#[Package('framework')]
class EntityAggregateTool extends McpToolResponse
{
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

        if (!$this->registry->has($entity)) {
            return $this->error(\sprintf('Entity "%s" not found. Use the shopware://entities resource for available entity names.', $entity));
        }

        if ($error = $this->requirePrivilege($context, $entity . ':read')) {
            return $error;
        }

        $definition = $this->registry->getByEntityName($entity);
        $repository = $this->registry->getRepository($entity);

        $aggregationDefs = $this->decodeJsonOrError($aggregations, 'aggregations');
        if (\is_string($aggregationDefs)) {
            return $aggregationDefs;
        }

        $filterDefs = $this->decodeJsonOrError($filters, 'filters');
        if (\is_string($filterDefs)) {
            return $filterDefs;
        }

        if (!\array_is_list($aggregationDefs)) {
            return $this->error('aggregations must be a JSON array of aggregation definitions.');
        }

        // Do not pass limit through the builder — RequestCriteriaBuilder rejects limit <= 0.
        // Set it directly on the Criteria object after parsing.
        $payload = ['aggregations' => $aggregationDefs];

        if ($filterDefs !== []) {
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
