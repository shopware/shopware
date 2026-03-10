<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 *
 * Searches products through the sales channel context, resolving prices,
 * customer group pricing, and visibility rules.
 */
#[McpTool(name: 'shopware-storefront-search', description: 'Search products with storefront context. Returns prices resolved for the sales channel including customer group pricing, tax rules, and product visibility. Use the properties parameter to filter by human-readable property names (e.g. {"Color": "Red", "Size": "42"}) -- the tool resolves names to IDs automatically. Use term for full-text search. Requires a salesChannelId (see shopware://sales-channels resource); optionally pass customerId for customer-specific pricing.')]
#[Package('framework')]
class StorefrontSearchTool
{
    use McpEntityIncludes;
    use McpToolResponse;

    /**
     * @internal
     *
     * @param SalesChannelRepository<ProductCollection> $productRepository
     */
    public function __construct(
        private readonly SalesChannelContextServiceInterface $contextService,
        private readonly SalesChannelRepository $productRepository,
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        private readonly RequestCriteriaBuilder $criteriaBuilder,
        private readonly JsonEntityEncoder $encoder,
        private readonly McpContextProvider $contextProvider,
        private readonly Connection $connection,
    ) {
    }

    public function __invoke(
        string $salesChannelId,
        string $criteria = '{}',
        ?string $customerId = null,
        string $properties = '{}',
        string $term = '',
    ): string {
        $context = $this->contextProvider->getContext();

        if ($error = $this->requirePrivilege($context, 'sales_channel:read')) {
            return $error;
        }

        $params = new SalesChannelContextServiceParameters(
            salesChannelId: $salesChannelId,
            token: Random::getAlphanumericString(32),
            customerId: $customerId,
        );

        $salesChannelContext = $this->contextService->get($params);

        $payload = json_decode($criteria, true, 512, \JSON_THROW_ON_ERROR);

        if ($term !== '') {
            $payload['term'] = $term;
        }

        $propertyFilter = $this->resolvePropertyFilters($properties);

        if (\is_string($propertyFilter)) {
            return $propertyFilter;
        }

        if ($propertyFilter !== []) {
            $payload['filter'] = [...($payload['filter'] ?? []), $propertyFilter];
        }

        $definition = $this->definitionRegistry->getByEntityName('product');

        $criteriaObj = $this->criteriaBuilder->fromArray(
            $payload,
            new Criteria(),
            $definition,
            $salesChannelContext->getContext(),
        );

        $this->applyDefaultIncludes($definition, $criteriaObj);

        $result = $this->productRepository->search($criteriaObj, $salesChannelContext);

        $limit = $criteriaObj->getLimit() ?? 25;

        $encoded = $this->encoder->encode($criteriaObj, $definition, $result->getEntities(), '/api');

        return $this->success($encoded, [
            'total' => $result->getTotal(),
            'page' => $criteriaObj->getOffset() ? (int) ($criteriaObj->getOffset() / $limit) + 1 : 1,
            'limit' => $limit,
            'salesChannelId' => $salesChannelId,
            'customerId' => $customerId,
            'currencyId' => $salesChannelContext->getCurrencyId(),
        ]);
    }

    /**
     * Resolves human-readable property group/option names to DAL filter arrays.
     * Uses the same AND/OR logic as PropertyListingFilterHandler:
     * OR within each property group, AND across groups.
     *
     * @return array<string, mixed>|string filter array, empty array if no properties, or error string
     */
    private function resolvePropertyFilters(string $propertiesJson): array|string
    {
        /** @var array<string, string>|null $properties */
        $properties = json_decode($propertiesJson, true, 512, \JSON_THROW_ON_ERROR);

        if (!\is_array($properties) || $properties === []) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(pgo.id)) as option_id,
                    LOWER(HEX(pgo.property_group_id)) as group_id,
                    pgt.name as group_name,
                    pgot.name as option_name
             FROM property_group_option pgo
             INNER JOIN property_group_option_translation pgot
                ON pgot.property_group_option_id = pgo.id
             INNER JOIN property_group_translation pgt
                ON pgt.property_group_id = pgo.property_group_id
             WHERE pgt.name IN (:groupNames)
               AND pgot.name IN (:optionNames)',
            [
                'groupNames' => array_keys($properties),
                'optionNames' => array_values($properties),
            ],
            [
                'groupNames' => ArrayParameterType::STRING,
                'optionNames' => ArrayParameterType::STRING,
            ],
        );

        $filtersByGroup = [];

        foreach ($rows as $row) {
            $requestedOption = $properties[$row['group_name']] ?? null;
            if ($requestedOption === $row['option_name']) {
                $filtersByGroup[$row['group_name']][] = $row['option_id'];
            }
        }

        $notFound = array_diff(array_keys($properties), array_keys($filtersByGroup));

        if ($notFound !== []) {
            $details = [];
            foreach ($notFound as $groupName) {
                $details[] = \sprintf('group "%s" with option "%s"', $groupName, $properties[$groupName]);
            }

            return $this->error('Could not resolve properties: ' . implode(', ', $details) . '. Check spelling or use shopware-entity-search on property_group / property_group_option to find available values.');
        }

        $filters = [];

        foreach ($filtersByGroup as $optionIds) {
            $filters[] = [
                'type' => 'multi',
                'operator' => 'or',
                'queries' => [
                    ['type' => 'equalsAny', 'field' => 'optionIds', 'value' => implode('|', $optionIds)],
                    ['type' => 'equalsAny', 'field' => 'propertyIds', 'value' => implode('|', $optionIds)],
                ],
            ];
        }

        if (\count($filters) === 1) {
            return $filters[0];
        }

        return ['type' => 'multi', 'operator' => 'and', 'queries' => $filters];
    }
}
