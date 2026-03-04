<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Log\Package;
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
#[McpTool(name: 'shopware-storefront-search', description: 'Search products with storefront context including resolved prices, customer group pricing, and visibility. Requires a salesChannelId; optionally pass customerId for customer-specific prices.')]
#[Package('framework')]
class StorefrontSearchTool
{
    /**
     * @param SalesChannelRepository<ProductCollection> $productRepository
     */
    public function __construct(
        private readonly SalesChannelContextServiceInterface $contextService,
        private readonly SalesChannelRepository $productRepository,
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        private readonly RequestCriteriaBuilder $criteriaBuilder,
    ) {
    }

    public function __invoke(string $salesChannelId, string $criteria = '{}', ?string $customerId = null): string
    {
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

        return json_encode([
            'total' => $result->getTotal(),
            'data' => array_values($result->getEntities()->jsonSerialize()),
            '_meta' => [
                'salesChannelId' => $salesChannelId,
                'customerId' => $customerId,
                'currencyId' => $salesChannelContext->getCurrencyId(),
                'page' => $criteriaObj->getOffset() ? (int) ($criteriaObj->getOffset() / $limit) + 1 : 1,
                'limit' => $limit,
            ],
        ], \JSON_THROW_ON_ERROR);
    }
}
