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
#[McpTool(name: 'shopware-entity-read', description: 'Read a single Shopware entity by its UUID. Use when you already have an entity ID. For searching by other fields, use shopware-entity-search instead. Returns {success, data: {id, ...fields}, _meta: {}}. Pass criteria JSON to include associations or select fields.')]
#[Package('framework')]
class EntityReadTool
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

    public function __invoke(string $entity, string $id, string $criteria = '{}'): string
    {
        $context = $this->contextProvider->getContext();

        if ($error = $this->requirePrivilege($context, $entity . ':read')) {
            return $error;
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

        $this->applyDefaultIncludes($definition, $criteriaObj);

        $result = $repository->search($criteriaObj, $context);
        $entityResult = $result->get($id);

        if ($entityResult === null) {
            return $this->error(\sprintf('Entity "%s" with ID "%s" not found.', $entity, $id));
        }

        $encoded = $this->encoder->encode($criteriaObj, $definition, $entityResult, '/api');

        return $this->success($encoded);
    }
}
