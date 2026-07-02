<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\StoreApiMcpContextProvider;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 *
 * @internal
 */
#[McpTool(name: 'shopware-store-api-context', title: 'Store API Context', description: 'Read the current Store API sales-channel context for this MCP session, including sales channel, language, currency, context token, and whether a customer is authenticated.')]
#[Package('framework')]
class StoreApiContextTool extends McpToolResponse
{
    public function __construct(
        private readonly StoreApiMcpContextProvider $contextProvider,
    ) {
    }

    public function __invoke(): string
    {
        $context = $this->contextProvider->getSalesChannelContext();

        if ($context === null) {
            return $this->error('No Store API sales-channel context is available for this MCP request.');
        }

        $customer = $context->getCustomer();

        return $this->success([
            'salesChannelId' => $context->getSalesChannelId(),
            'token' => $context->getToken(),
            'languageId' => $context->getLanguageId(),
            'currencyId' => $context->getCurrencyId(),
            'customerAuthenticated' => $customer !== null,
            'customerId' => $customer?->getId(),
        ]);
    }
}
