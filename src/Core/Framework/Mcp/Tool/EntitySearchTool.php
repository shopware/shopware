<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Shopware\Core\Framework\Api\Acl\AclCriteriaValidator;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\SearchRequestException;
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
    name: 'shopware-entity-search',
    title: 'Entity Search',
    description: 'Search, list and filter Shopware entities of any type — orders, products, customers, categories and the rest. Use this to LIST or page through records ("the last 10 orders", "all customers in Berlin"), and to look one up by productNumber or any other exact field value, including as the first step in Storefront cart/checkout workflows. Sort with criteria.sort, e.g. [{"field":"orderDateTime","order":"DESC"}]. For count/sum/average reporting, use shopware-entity-aggregate instead (the _meta.total here is pagination metadata, not a reporting count). Accepts Admin API criteria JSON. Returns {success, data: [...], _meta: {total, page, limit}}. If you don\'t already know the field names, shopware-entity-schema will tell you.'
)]
#[McpToolDependsOn('shopware-entity-schema')]
#[McpToolGroup('entity')]
#[McpToolRequires(entityParam: 'entity', operations: ['read'])]
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
        private readonly AclCriteriaValidator $criteriaValidator,
    ) {
    }

    public function __invoke(
        #[Schema(description: 'Entity name to search, e.g. "order", "product" or "customer". See the shopware://entities resource for the full list.')]
        string $entity,
        #[Schema(description: 'A JSON OBJECT of Admin API criteria, as a string — "filter", "sort", "associations", "includes". E.g. {"sort":[{"field":"orderDateTime","order":"DESC"}]} for the most recent first, or {"filter":[{"type":"equals","field":"productNumber","value":"SW10001"}]} to look one up. Defaults to no criteria.')]
        string $criteria = '{}',
        #[Schema(description: 'Records per page, 1-500.')]
        int $limit = 25,
        #[Schema(description: 'Page number, starting at 1.')]
        int $page = 1,
        #[Schema(description: 'Free-text search across the entity\'s searchable fields. Prefer an exact "filter" in `criteria` when you know the field.')]
        string $term = '',
    ): string {
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

        $payload['limit'] ??= $limit;
        $payload['total-count-mode'] ??= Criteria::TOTAL_COUNT_MODE_EXACT;
        if ($page > 1) {
            $payload['page'] = $page;
        }
        if ($term !== '') {
            $payload['term'] = $term;
        }

        try {
            $criteriaObj = $this->criteriaBuilder->fromArray(
                $payload,
                new Criteria(),
                $definition,
                $context,
            );
        } catch (SearchRequestException|DataAbstractionLayerException $e) {
            return $this->invalidCriteriaError($e);
        }

        // Criteria can reference associated entities that require their own read privileges
        // (same association ACL model as the Admin API).
        $missing = $this->criteriaValidator->validate($entity, $criteriaObj, $context);
        if ($missing !== []) {
            return $this->missingPrivilegesError($missing);
        }

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
