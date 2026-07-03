<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Context;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 *
 * @internal
 */
#[Package('framework')]
class StoreApiMcpContextProvider implements McpContextProviderInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getSalesChannelContext(): ?SalesChannelContext
    {
        $request = $this->requestStack->getMainRequest();

        if ($request === null) {
            return null;
        }

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);

        return $context instanceof SalesChannelContext ? $context : null;
    }

    public function getContext(): Context
    {
        $salesChannelContext = $this->getSalesChannelContext();

        return $salesChannelContext?->getContext() ?? Context::createCLIContext();
    }
}
