<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Controller;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Authentication\McpOAuthMetadata;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Serves the OAuth discovery documents MCP clients fetch after a `401` from `/api/_mcp`.
 *
 * The routes have no route scope, because they live outside `/api` and need neither a sales channel
 * nor an authenticated context. {@see McpOAuthMetadataRouteScopeWhitelist} allows them.
 * The root `/.well-known/oauth-authorization-server` stays free for extensions.
 *
 * @internal
 *
 * @experimental stableVersion:v6.8.0
 */
#[Package('framework')]
class McpOAuthMetadataController
{
    public function __construct(
        private readonly McpOAuthMetadata $metadata,
    ) {
    }

    #[Route(
        path: McpOAuthMetadata::PROTECTED_RESOURCE_PREFIX . McpOAuthMetadata::RESOURCE_PATH,
        name: 'mcp.oauth.protected_resource_metadata',
        methods: [Request::METHOD_GET],
    )]
    public function protectedResource(Request $request): JsonResponse
    {
        return $this->createResponse($this->metadata->buildProtectedResourceMetadata($request));
    }

    #[Route(
        path: McpOAuthMetadata::AUTHORIZATION_SERVER_PREFIX . McpOAuthMetadata::ISSUER_PATH,
        name: 'mcp.oauth.authorization_server_metadata',
        methods: [Request::METHOD_GET],
    )]
    public function authorizationServer(Request $request): JsonResponse
    {
        return $this->createResponse($this->metadata->buildAuthorizationServerMetadata($request));
    }

    /**
     * @param array<string, mixed> $document
     */
    private function createResponse(array $document): JsonResponse
    {
        $response = new JsonResponse($document);
        $response->setEncodingOptions($response->getEncodingOptions() | \JSON_UNESCAPED_SLASHES);

        return $response;
    }
}
