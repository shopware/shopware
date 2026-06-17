<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Controller;

use Mcp\Server;
use Mcp\Server\Transport\StreamableHttpTransport;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\RateLimit\McpRateLimiter;
use Shopware\Core\Framework\Mcp\Session\McpSessionIdValidator;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 *
 * Store API entry point for the MCP protocol over HTTP.
 * This endpoint uses the normal Store API sales-channel access key and
 * sales-channel context token instead of Admin API OAuth/integration keys.
 *
 * No per-integration allowlist is applied here. The Admin API MCP endpoint
 * restricts capabilities per integration/user via McpAllowlistProvider, but
 * the Store API is intentionally open: any authenticated sales-channel client
 * can access all registered Store API MCP capabilities. Fine-grained access
 * control at the sales-channel level is a deliberate future extension point.
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
#[Package('framework')]
class StoreApiMcpServerController
{
    /**
     * @internal
     *
     * The first five params are nullable because they are injected via
     * nullOnInvalid(): when the MCP bundle is absent they resolve to null.
     * Once MCP_SERVER is stable (v6.8.0) remove the nullable types and the null guards in handle().
     */
    public function __construct(
        private readonly ?Server $server,
        private readonly ?HttpMessageFactoryInterface $httpMessageFactory,
        private readonly ?HttpFoundationFactoryInterface $httpFoundationFactory,
        private readonly ?ResponseFactoryInterface $responseFactory,
        private readonly ?StreamFactoryInterface $streamFactory,
        private readonly McpRateLimiter $rateLimiter,
        private readonly McpSessionIdValidator $sessionIdValidator,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Single entry point for the Store API MCP endpoint.
     *
     * The bare `/store-api/_mcp` route is the standard JSON-RPC transport used by
     * MCP clients; it is also the only one that supports batch requests and
     * notifications. The per-method routes are typed convenience endpoints: each
     * carries its MCP method name as the `command` route default, which is
     * injected into the JSON-RPC body (see injectMethod). This lets the OpenAPI
     * schema describe one request body and one response per method instead of a
     * single endpoint with a method-keyed `oneOf`. On the bare route `$command`
     * is null and the body is forwarded untouched.
     */
    #[Route(path: '/store-api/_mcp', name: 'store-api.mcp.endpoint', defaults: ['auth_required' => true], methods: [Request::METHOD_GET, Request::METHOD_POST, Request::METHOD_DELETE, Request::METHOD_OPTIONS])]
    #[Route(path: '/store-api/_mcp/initialize', name: 'store-api.mcp.command.initialize', defaults: ['command' => 'initialize', 'auth_required' => true], methods: [Request::METHOD_POST])]
    #[Route(path: '/store-api/_mcp/ping', name: 'store-api.mcp.command.ping', defaults: ['command' => 'ping', 'auth_required' => true], methods: [Request::METHOD_POST])]
    #[Route(path: '/store-api/_mcp/tools/list', name: 'store-api.mcp.command.tools-list', defaults: ['command' => 'tools/list', 'auth_required' => true], methods: [Request::METHOD_POST])]
    #[Route(path: '/store-api/_mcp/tools/call', name: 'store-api.mcp.command.tools-call', defaults: ['command' => 'tools/call', 'auth_required' => true], methods: [Request::METHOD_POST])]
    #[Route(path: '/store-api/_mcp/resources/list', name: 'store-api.mcp.command.resources-list', defaults: ['command' => 'resources/list', 'auth_required' => true], methods: [Request::METHOD_POST])]
    #[Route(path: '/store-api/_mcp/resources/templates/list', name: 'store-api.mcp.command.resources-templates-list', defaults: ['command' => 'resources/templates/list', 'auth_required' => true], methods: [Request::METHOD_POST])]
    #[Route(path: '/store-api/_mcp/resources/read', name: 'store-api.mcp.command.resources-read', defaults: ['command' => 'resources/read', 'auth_required' => true], methods: [Request::METHOD_POST])]
    #[Route(path: '/store-api/_mcp/prompts/list', name: 'store-api.mcp.command.prompts-list', defaults: ['command' => 'prompts/list', 'auth_required' => true], methods: [Request::METHOD_POST])]
    #[Route(path: '/store-api/_mcp/prompts/get', name: 'store-api.mcp.command.prompts-get', defaults: ['command' => 'prompts/get', 'auth_required' => true], methods: [Request::METHOD_POST])]
    public function handle(Request $request, ?string $command = null): Response
    {
        if (!Feature::isActive('MCP_SERVER')
            || $this->server === null
            || $this->httpMessageFactory === null
            || $this->httpFoundationFactory === null
            || $this->responseFactory === null
            || $this->streamFactory === null
        ) {
            return new Response(null, Response::HTTP_NOT_FOUND);
        }

        $this->sessionIdValidator->validate($request);
        $this->rateLimiter->enforceForStoreApi($request);

        $this->logger?->debug('Store API MCP request', [
            'method' => $request->getMethod(),
            'command' => $command,
            'clientIp' => $request->getClientIp(),
        ]);

        $psrRequest = $this->httpMessageFactory->createRequest($request);
        if ($command !== null) {
            $psrRequest = $this->injectMethod($psrRequest, $command);
        }

        $transport = new StreamableHttpTransport(
            $psrRequest,
            $this->responseFactory,
            $this->streamFactory,
            logger: $this->logger,
        );

        $psrResponse = $this->server->run($transport);
        $streamed = strtolower($psrResponse->getHeaderLine('Content-Type')) === 'text/event-stream';

        return $this->httpFoundationFactory->createResponse($psrResponse, $streamed);
    }

    /**
     * Rewrites the request body so the path-derived command becomes the
     * JSON-RPC `method`. Only a single JSON-RPC object is rewritten; batch
     * arrays and malformed bodies pass through untouched and are handled by the
     * SDK transport (batch callers should use the bare /store-api/_mcp endpoint).
     */
    private function injectMethod(ServerRequestInterface $psrRequest, string $command): ServerRequestInterface
    {
        $raw = (string) $psrRequest->getBody();
        $decoded = $raw === '' ? [] : json_decode($raw, true);

        if (!\is_array($decoded) || array_is_list($decoded)) {
            return $psrRequest;
        }

        $decoded['jsonrpc'] ??= '2.0';
        $decoded['method'] ??= $command;

        \assert($this->streamFactory !== null);
        $body = $this->streamFactory->createStream(json_encode($decoded, \JSON_THROW_ON_ERROR));

        return $psrRequest->withBody($body);
    }
}
