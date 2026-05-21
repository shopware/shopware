<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Transport\Mcp\Tool;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Capability\Checkout\CheckoutCapability;
use Shopware\Core\Framework\Ucp\Capability\Checkout\CheckoutStatus;
use Shopware\Core\Framework\Ucp\Negotiation\UcpRequestContext;
use Shopware\Core\Framework\Ucp\Transport\Mcp\AbstractUcpMcpTool;
use Shopware\Core\Framework\Ucp\Transport\Mcp\UcpMcpTool;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 */
#[UcpMcpTool(name: 'cancel_checkout', capability: CheckoutCapability::NAME, description: 'Cancel a checkout session')]
#[Package('framework')]
class CancelCheckoutTool extends AbstractUcpMcpTool
{
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

        return ['id' => $id, 'status' => CheckoutStatus::CANCELED];
    }
}
