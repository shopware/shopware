<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Attribute\McpToolDependsOn;
use Shopware\Core\Framework\Mcp\Attribute\McpToolRequires;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[McpTool(name: 'shopware-entity-search', title: 'Entity Search', description: 'Search and filter Shopware entities — use this to look up a product by its productNumber or any exact field value, including as the first step in Storefront cart/checkout workflows. For count/sum/average reporting, use shopware-entity-aggregate instead (the _meta.total here is pagination metadata, not a reporting count). Accepts Admin API criteria JSON. Returns {success, data: [...], _meta: {total, page, limit}}. Use shopware-entity-schema first if you need field names.')]
#[McpToolDependsOn('shopware-entity-schema')]
#[McpToolRequires(entityParam: 'entity', operations: ['read'])]
#[Package('framework')]
class EntitySearchTool extends McpToolResponse
{
    use McpEntityIncludes;

    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $registry,
        private readonly RequestCriteriaBuilder $criteriaBuilder,
        private readonly McpContextProvider $contextProvider,
        private readonly JsonEntityEncoder $encoder,
    ) {
    }

    public function __invoke(string $entity, string $criteria = '{}', int $limit = 25, int $page = 1, string $term = ''): string
    {
        $context = $this->contextProvider->getContext();

        if (!$this->registry->has($entity)) { // @codeCoverageIgnore
            return $this->error(\sprintf('Entity "%s" not found. Use the shopware://entities resource for available entity names.', $entity)); // @codeCoverageIgnore
        }

        if ($error = $this->requirePrivilege($context, $entity . ':read')) {
            return $error;
        }

        $payload = $this->decodeJsonOrError($criteria, 'criteria');
        if (\is_string($payload)) {
            return $payload;
        }

        $definition = $this->registry->getByEntityName($entity);
        $repository = $this->registry->getRepository($entity);

        $payload['limit'] ??= $limit;
        $payload['total-count-mode'] ??= Criteria::TOTAL_COUNT_MODE_EXACT;
        if ($page > 1) {
            $payload['page'] = $page;
        }
        if ($term !== '') {
            $payload['term'] = $term;
        }

        $criteriaObj = $this->criteriaBuilder->fromArray(
            $payload,
            new Criteria(),
            $definition,
            $context,
        );

        $this->applyDefaultIncludes($definition, $criteriaObj);

        $result = $repository->search($criteriaObj, $context);

        $limit = $criteriaObj->getLimit() ?? 25;

        $encoded = $this->encoder->encode($criteriaObj, $definition, $result->getEntities(), '/api');

        return $this->success($encoded, [
            'total' => $result->getTotal(),
            'page' => $criteriaObj->getOffset() ? (int) ($criteriaObj->getOffset() / $limit) + 1 : 1,
            'limit' => $limit,
        ]);
    }
}
