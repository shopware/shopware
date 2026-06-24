<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Context;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 *
 * Bridges the authenticated HTTP request context into MCP tool invocations.
 * The MCP bundle's HTTP transport processes requests through Shopware's API middleware,
 * so the Context is already resolved and attached to the request by ApiRequestContextResolver.
 */
#[Package('framework')]
class McpContextProvider implements McpContextProviderInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getContext(): Context
    {
        $request = $this->requestStack->getMainRequest();

        if ($request !== null) {
            $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);

            if ($context instanceof Context) {
                return $context;
            }
        }

        return Context::createCLIContext();
    }
}
