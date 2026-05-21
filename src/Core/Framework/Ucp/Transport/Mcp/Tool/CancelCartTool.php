<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Transport\Mcp\Tool;

use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartDeleteRoute;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Capability\Cart\CartCapability;
use Shopware\Core\Framework\Ucp\Negotiation\UcpRequestContext;
use Shopware\Core\Framework\Ucp\Transport\Mcp\AbstractUcpMcpTool;
use Shopware\Core\Framework\Ucp\Transport\Mcp\UcpMcpTool;
use Shopware\Core\Framework\Ucp\UcpException;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 */
#[UcpMcpTool(name: 'cancel_cart', capability: CartCapability::NAME, description: 'Cancel a cart session')]
#[Package('framework')]
class CancelCartTool extends AbstractUcpMcpTool
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractCartDeleteRoute $cartDeleteRoute,
        private readonly AbstractSalesChannelContextFactory $salesChannelContextFactory,
    ) {
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['id'],
            'properties' => ['id' => ['type' => 'string']],
        ];
    }

    public function invoke(array $arguments, UcpRequestContext $context): array
    {
        $id = \is_string($arguments['id'] ?? null) ? $arguments['id'] : '';
        if ($id === '') {
            throw UcpException::mcpToolInvalidArguments('cancel_cart', 'cart id required');
        }

        // Rebind context to the cart token so `cartDeleteRoute` targets the
        // correct cart — `delete()` uses `$context->getToken()`, which is the
        // resolver's synthetic token unless we recreate the context.
        $sc = $this->salesChannelContextFactory->create(
            $id,
            $context->salesChannelContext->getSalesChannelId(),
            array_filter([
                SalesChannelContextService::DOMAIN_ID => $context->salesChannelContext->getDomainId(),
            ])
        );

        $this->cartDeleteRoute->delete($sc);

        return ['id' => $id, 'status' => 'canceled'];
    }
}
