<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Api\Acl\AclCriteriaValidator;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Attribute\McpToolDependsOn;
use Shopware\Core\Framework\Mcp\Attribute\McpToolGroup;
use Shopware\Core\Framework\Mcp\Attribute\McpToolRequires;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;

/**
 * @experimental stableVersion:v6.8.0
 */
#[Package('framework')]
#[McpTool(
    name: 'shopware-entity-read',
    title: 'Entity Read',
    description: 'Read a single Shopware entity by its UUID. Use when you already have an entity ID. For searching by other fields, use shopware-entity-search instead. Returns {success, data: {id, ...fields}, _meta: {}}. Pass criteria JSON to include associations or select fields.'
)]
#[McpToolDependsOn('shopware-entity-schema')]
#[McpToolGroup('entity')]
#[McpToolRequires(entityParam: 'entity', operations: ['read'])]
class EntityReadTool extends McpToolResponse
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
        private readonly AclCriteriaValidator $criteriaValidator,
    ) {
    }

    /**
     * @param string $entity Entity name to read, e.g. "order" or "product". See the shopware://entities resource for the full list.
     * @param string $id The entity's UUID (32-character hex). To find a record by any other field, use shopware-entity-search instead.
     * @param string $criteria A JSON OBJECT of Admin API criteria, as a string — most usefully "associations" to include related data, e.g. {"associations":{"lineItems":{}}} on an order, and "includes" to trim the response. Defaults to no criteria.
     */
    public function __invoke(string $entity, string $id, string $criteria = '{}'): string
    {
        $context = $this->contextProvider->getContext();

        if (!$this->registry->has($entity)) {
            return $this->error(\sprintf('Entity "%s" not found. Use the shopware://entities resource for available entity names.', $entity));
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

        $criteriaObj = $this->criteriaBuilder->fromArray(
            $payload,
            new Criteria([$id]),
            $definition,
            $context,
        );

        // Criteria can reference associated entities that require their own read privileges
        // (same association ACL model as the Admin API).
        $missing = $this->criteriaValidator->validate($entity, $criteriaObj, $context);
        if ($missing !== []) {
            return $this->missingPrivilegesError($missing);
        }

        $this->applyDefaultIncludes($definition, $criteriaObj);

        $result = $repository->search($criteriaObj, $context);
        $entityResult = $result->getEntities()->get($id);

        if ($entityResult === null) {
            return $this->error(\sprintf('Entity "%s" with ID "%s" not found.', $entity, $id));
        }

        $encoded = $this->encoder->encode($criteriaObj, $definition, $entityResult, '/api');

        return $this->success($encoded);
    }
}
