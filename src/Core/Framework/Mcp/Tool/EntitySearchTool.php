<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[McpTool(name: 'shopware-entity-search', description: 'Search Shopware entities using the Admin API criteria format. Supports filters, sorting, pagination, aggregations, and associations. Pass criteria as JSON.')]
#[Package('framework')]
class EntitySearchTool
{
    public function __construct(
        private readonly DefinitionInstanceRegistry $registry,
        private readonly RequestCriteriaBuilder $criteriaBuilder,
        private readonly McpContextProvider $contextProvider,
    ) {
    }

    public function __invoke(string $entity, string $criteria = '{}'): string
    {
        $context = $this->contextProvider->getContext();

        if (!$context->isAllowed($entity . ':read')) {
            return json_encode(['error' => \sprintf('Missing privilege: %s:read', $entity)], \JSON_THROW_ON_ERROR);
        }

        $definition = $this->registry->getByEntityName($entity);
        $repository = $this->registry->getRepository($entity);

        $payload = json_decode($criteria, true, 512, \JSON_THROW_ON_ERROR);

        $criteriaObj = $this->criteriaBuilder->fromArray(
            $payload,
            new Criteria(),
            $definition,
            $context,
        );

        $result = $repository->search($criteriaObj, $context);

        $limit = $criteriaObj->getLimit() ?? 25;

        return json_encode([
            'total' => $result->getTotal(),
            'data' => array_values($result->getEntities()->jsonSerialize()),
            '_meta' => [
                'page' => $criteriaObj->getOffset() ? (int) ($criteriaObj->getOffset() / $limit) + 1 : 1,
                'limit' => $limit,
            ],
        ], \JSON_THROW_ON_ERROR);
    }
}
