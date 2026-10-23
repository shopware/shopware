<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Http;

use Mcp\Server\Transport\Http\Middleware\DnsRebindingProtectionMiddleware;
use Mcp\Server\Transport\StreamableHttpTransport;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\McpAllowedHostsProvider;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @experimental stableVersion:v6.8.0
 *
 * @internal
 *
 * Shared HTTP glue for the Admin and Store API MCP controllers: builds the Streamable HTTP
 * transport (seeded with the shop's own hosts for DNS-rebinding protection) and converts the SDK's
 * PSR response into a Symfony response.
 *
 * The PhpMcp bundle factories are nullable because they are injected via nullOnInvalid(): when the
 * MCP bundle is absent they resolve to null. Callers must check {@see self::isAvailable()} before
 * building a transport. Once MCP is stable (v6.8.0) the nullable types can be removed.
 */
#[Package('framework')]
class McpHttpTransportFactory
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ?HttpMessageFactoryInterface $httpMessageFactory,
        private readonly ?ResponseFactoryInterface $responseFactory,
        private readonly ?StreamFactoryInterface $streamFactory,
        private readonly ?HttpFoundationFactoryInterface $httpFoundationFactory,
        private readonly McpAllowedHostsProvider $allowedHostsProvider,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->httpMessageFactory !== null
            && $this->responseFactory !== null
            && $this->streamFactory !== null
            && $this->httpFoundationFactory !== null;
    }

    public function createTransport(Request $request): StreamableHttpTransport
    {
        \assert($this->httpMessageFactory !== null && $this->responseFactory !== null && $this->streamFactory !== null);

        return new StreamableHttpTransport(
            $this->httpMessageFactory->createRequest($request),
            $this->responseFactory,
            $this->streamFactory,
            logger: $this->logger,
            middleware: $this->middleware($request),
        );
    }

    public function createStream(string $content): StreamInterface
    {
        \assert($this->streamFactory !== null);

        return $this->streamFactory->createStream($content);
    }

    public function createResponse(PsrResponseInterface $psrResponse): Response
    {
        \assert($this->httpFoundationFactory !== null);

        return $this->httpFoundationFactory->createResponse($psrResponse, false);
    }

    /**
     * The SDK's default {@see DnsRebindingProtectionMiddleware} only allows localhost, which rejects
     * every request that reaches Shopware through its configured hostname. Keep the mitigation but
     * seed it with the shop's own hosts (APP_URL + sales channel domains) so legitimate clients pass
     * while rebinding attempts are still blocked.
     *
     * The SDK accepts a cross-origin request only for an exact origin (scheme, host and port), while
     * Shopware allows every origin on one of its hosts, as mcp/sdk 0.8 did. Browser clients such as
     * the MCP Inspector run on a port of their own (`http://localhost:6274`), which no static list
     * can name. The request's own origin is therefore passed when its host is allowed.
     *
     * @return list<MiddlewareInterface>
     */
    private function middleware(Request $request): array
    {
        $allowedHosts = $this->allowedHostsProvider->getAllowedHosts();
        $allowedOrigins = [];

        $origin = $request->headers->get('Origin');
        if ($origin !== null) {
            $originHost = parse_url($origin, \PHP_URL_HOST);
            if (\is_string($originHost) && \in_array(strtolower($originHost), $allowedHosts, true)) {
                $allowedOrigins[] = $origin;
            }
        }

        return array_map(
            static fn (MiddlewareInterface $middleware): MiddlewareInterface => $middleware instanceof DnsRebindingProtectionMiddleware
                ? new DnsRebindingProtectionMiddleware($allowedHosts, $allowedOrigins)
                : $middleware,
            StreamableHttpTransport::defaultMiddleware(),
        );
    }
}
