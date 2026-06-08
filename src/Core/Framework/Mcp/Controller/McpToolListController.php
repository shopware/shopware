<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Controller;

use Mcp\Server\Builder;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlist;
use Shopware\Core\Framework\Mcp\McpCapabilityCatalog;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 *
 * Provides the list of registered MCP capabilities so the Admin UI can populate
 * the per-integration allowlist selector.
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
#[Package('framework')]
class McpToolListController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ?Builder $builder,
        private readonly ?McpCapabilityCatalog $catalog,
    ) {
    }

    #[Route(
        path: '/api/_action/mcp/tools',
        name: 'api.action.mcp.tools',
        defaults: [
            'auth_required' => true,
            PlatformRequest::ATTRIBUTE_ACL => ['integration_mcp.editor'],
        ],
        methods: ['GET'],
    )]
    public function list(): JsonResponse
    {
        if (!Feature::isActive('MCP_SERVER') || $this->builder === null || $this->catalog === null) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        $this->builder->build();

        return new JsonResponse($this->catalog->enrichedTools());
    }

    #[Route(
        path: '/api/_action/mcp/capabilities',
        name: 'api.action.mcp.capabilities',
        defaults: [
            'auth_required' => true,
            PlatformRequest::ATTRIBUTE_ACL => ['integration_mcp.editor'],
        ],
        methods: ['GET'],
    )]
    public function capabilities(): JsonResponse
    {
        if (!Feature::isActive('MCP_SERVER') || $this->builder === null || $this->catalog === null) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        $this->builder->build();

        return new JsonResponse([
            McpAllowlist::TOOLS => $this->catalog->enrichedTools(),
            McpAllowlist::RESOURCES => $this->catalog->enrichedResources(),
            McpAllowlist::PROMPTS => $this->catalog->enrichedPrompts(),
        ]);
    }
}
