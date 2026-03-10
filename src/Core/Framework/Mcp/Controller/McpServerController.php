<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Controller;

use Mcp\Server;
use Mcp\Server\Transport\StreamableHttpTransport;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\McpException;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\PlatformRequest;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 *
 * Shopware-aware entry point for the MCP protocol over HTTP.
 * Applies Shopware's Admin API authentication and route scoping, then delegates
 * the actual protocol handling to the Symfony MCP Server.
 */
#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('framework')]
class McpServerController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Server $server,
        private readonly HttpMessageFactoryInterface $httpMessageFactory,
        private readonly HttpFoundationFactoryInterface $httpFoundationFactory,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly RateLimiter $rateLimiter,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    #[Route(path: '/api/_mcp', name: 'api.mcp.endpoint', defaults: ['auth_required' => true], methods: ['GET', 'POST', 'DELETE', 'OPTIONS'])]
    public function handle(Request $request): Response
    {
        $this->rateLimit($request);

        $this->logger?->debug('MCP request', [
            'method' => $request->getMethod(),
            'clientIp' => $request->getClientIp(),
        ]);

        $transport = new StreamableHttpTransport(
            $this->httpMessageFactory->createRequest($request),
            $this->responseFactory,
            $this->streamFactory,
            logger: $this->logger,
        );

        $psrResponse = $this->server->run($transport);
        $streamed = $psrResponse->getHeaderLine('Content-Type') === 'text/event-stream';

        return $this->httpFoundationFactory->createResponse($psrResponse, $streamed);
    }

    private function rateLimit(Request $request): void
    {
        $key = $request->attributes->getString(PlatformRequest::ATTRIBUTE_OAUTH_ACCESS_TOKEN_ID)
            ?: $request->getClientIp()
            ?: 'unknown';

        try {
            $this->rateLimiter->ensureAccepted(RateLimiter::MCP, $key);
        } catch (RateLimitExceededException $e) {
            throw McpException::throttled($e->getWaitTime(), $e);
        }
    }
}
