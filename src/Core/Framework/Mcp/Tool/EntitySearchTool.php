<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[McpTool(name: 'shopware-entity-search', description: 'Primary data retrieval tool. Search Shopware entities using the Admin API criteria format. Use the top-level term, limit, and page parameters for simple queries, or pass full criteria JSON for filters, sorting, aggregations, associations, and includes/excludes. Returns {success, data: [...], _meta: {total, page, limit}}. Use shopware-entity-schema first if you need field names.')]
#[Package('framework')]
class EntitySearchTool
{
    use McpEntityIncludes;
    use McpToolResponse;

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

        if ($error = $this->requirePrivilege($context, $entity . ':read')) {
            return $error;
        }

        $definition = $this->registry->getByEntityName($entity);
        $repository = $this->registry->getRepository($entity);

        $payload = json_decode($criteria, true, 512, \JSON_THROW_ON_ERROR);

        if ($limit !== 25) {
            $payload['limit'] = $limit;
        }
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
