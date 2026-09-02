<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Api\Acl\AclCriteriaValidator;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\SearchRequestException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Attribute\McpToolDependsOn;
use Shopware\Core\Framework\Mcp\Attribute\McpToolGroup;
use Shopware\Core\Framework\Mcp\Attribute\McpToolRequires;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;

/**
 * @experimental stableVersion:v6.8.0
 *
 * Dedicated aggregation tool that loads zero entity rows and returns only
 * aggregation results, keeping the response well within the 100 KB limit.
 */
#[Package('framework')]
#[McpTool(
    name: 'shopware-entity-aggregate',
    title: 'Entity Aggregate',
    description: 'The correct tool for count, sum, average, and other aggregate questions. Use this — not shopware-entity-search — for any \'how many\', \'total value\', or \'average\' query. Note: entity-search\'s _meta.total is pagination metadata, not a reporting count. Supports: count, avg, sum, min, max, terms, date-histogram. Returns only aggregation results, no entity rows. Pass aggregation definitions as Admin API criteria JSON.'
)]
#[McpToolDependsOn('shopware-entity-schema')]
#[McpToolGroup('entity')]
#[McpToolRequires(entityParam: 'entity', operations: ['read'])]
class EntityAggregateTool extends McpToolResponse
{
    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $registry,
        private readonly RequestCriteriaBuilder $criteriaBuilder,
        private readonly McpContextProvider $contextProvider,
        private readonly AclCriteriaValidator $criteriaValidator,
    ) {
    }

    /**
     * @param string $entity Entity name to aggregate over, e.g. "order" or "product". See the shopware://entities resource for the full list.
     * @param string $aggregations A JSON ARRAY of Admin API aggregation definitions, as a string. Each element needs "name" and "type"; every type except "filter" also needs "field" — e.g. [{"name":"order_count","type":"count","field":"id"}] to count orders, or [{"name":"revenue","type":"sum","field":"amountTotal"}] to total them. A "filter" element takes no "field": it wraps another aggregation, so it needs "filter" (an array of filter definitions) and "aggregation" (the nested definition to apply inside it). A bare object rather than an array is the most common mistake and is rejected.
     * @param string $filters A JSON array of Admin API filter definitions, as a string, narrowing what is aggregated — e.g. [{"type":"equals","field":"stateId","value":"..."}]. Defaults to no filter.
     */
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

        try {
            $criteriaObj = $this->criteriaBuilder->fromArray(
                $payload,
                new Criteria(),
                $definition,
                $context,
            );
        } catch (SearchRequestException|DataAbstractionLayerException $e) {
            // Expected/business error, so it is answered rather than propagated:
            // the caller sent an aggregation this entity cannot express, and the
            // parser already knows which element and why. Scoped to this call,
            // so an unexpected throwable from the search below still reaches the
            // log untouched, per the policy in McpToolResponse.
            return $this->invalidCriteriaError($e);
        }

        // Aggregations and filters can reference associated entities that require their own
        // read privileges (same association ACL model as the Admin API).
        $missing = $this->criteriaValidator->validate($entity, $criteriaObj, $context);
        if ($missing !== []) {
            return $this->missingPrivilegesError($missing);
        }

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
