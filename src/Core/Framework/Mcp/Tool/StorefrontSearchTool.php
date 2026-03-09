<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

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
#[McpTool(name: 'shopware-storefront-search', description: 'Search products with storefront context. Unlike shopware-entity-search, this returns prices resolved for the sales channel including customer group pricing, tax rules, and product visibility. Use this when you need real storefront prices. Requires a salesChannelId (see shopware://sales-channels resource); optionally pass customerId for customer-specific pricing.')]
#[Package('framework')]
class StorefrontSearchTool
{
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
    ) {
    }

    public function __invoke(string $salesChannelId, string $criteria = '{}', ?string $customerId = null): string
    {
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

        $definition = $this->definitionRegistry->getByEntityName('product');

        $criteriaObj = $this->criteriaBuilder->fromArray(
            $payload,
            new Criteria(),
            $definition,
            $salesChannelContext->getContext(),
        );

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
}
