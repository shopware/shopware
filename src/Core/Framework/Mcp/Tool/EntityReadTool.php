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
#[McpTool(name: 'shopware-entity-read', description: 'Read a single Shopware entity by its ID. Optionally include associations via criteria JSON.')]
#[Package('framework')]
class EntityReadTool
{
    public function __construct(
        private readonly DefinitionInstanceRegistry $registry,
        private readonly RequestCriteriaBuilder $criteriaBuilder,
        private readonly McpContextProvider $contextProvider,
    ) {
    }

    public function __invoke(string $entity, string $id, string $criteria = '{}'): string
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
            new Criteria([$id]),
            $definition,
            $context,
        );

        $result = $repository->search($criteriaObj, $context);
        $entityResult = $result->get($id);

        if ($entityResult === null) {
            return json_encode(['error' => \sprintf('Entity "%s" with ID "%s" not found.', $entity, $id)], \JSON_THROW_ON_ERROR);
        }

        return json_encode([
            'data' => $entityResult->jsonSerialize(),
        ], \JSON_THROW_ON_ERROR);
    }
}
