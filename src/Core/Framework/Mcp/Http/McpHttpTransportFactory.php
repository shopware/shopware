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

    /**
     * @param bool $sessionNotFound answer with the SDK's unknown-session `404` instead of handling the request,
     *                              for a session the caller may not use (see `McpSessionPrincipalBinding`)
     */
    public function createTransport(Request $request, bool $sessionNotFound = false): StreamableHttpTransport
    {
        \assert($this->httpMessageFactory !== null && $this->responseFactory !== null && $this->streamFactory !== null);

        $middleware = $this->middleware($request);
        if ($sessionNotFound) {
            $middleware[] = new McpSessionNotFoundMiddleware($this->responseFactory, $this->streamFactory);
        }

        return new StreamableHttpTransport(
            $this->httpMessageFactory->createRequest($request),
            $this->responseFactory,
            $this->streamFactory,
            logger: $this->logger,
            middleware: $middleware,
        );
    }

    public function createStream(string $content): StreamInterface
    {
        \assert($this->streamFactory !== null);

        return $this->streamFactory->createStream($content);
    }

    /**
     * The SDK's text/event-stream body can be a `CallbackStream` that resumes the suspended request and
     * echoes each frame when read, for example progress of a tool call or a `subscriptions/listen` stream.
     * It must stay unread until Symfony has sent the headers, so it is passed on as a streamed response.
     */
    public function createResponse(PsrResponseInterface $psrResponse): Response
    {
        \assert($this->httpFoundationFactory !== null);

        $streamed = str_starts_with(strtolower($psrResponse->getHeaderLine('Content-Type')), 'text/event-stream');

        return $this->httpFoundationFactory->createResponse($psrResponse, $streamed);
    }

    /**
     * The SDK's default {@see DnsRebindingProtectionMiddleware} only allows localhost, which rejects
     * every request that reaches Shopware through its configured hostname. Keep the mitigation but
     * seed it with the shop's own hosts (APP_URL + sales channel domains) so legitimate clients pass
     * while rebinding attempts are still blocked.
     *
     * A cross-origin request needs an exact origin, see {@see McpAllowedHostsProvider::isAllowedOrigin()}.
     * The SDK keeps checking same-origin requests itself.
     *
     * @return list<MiddlewareInterface>
     */
    private function middleware(Request $request): array
    {
        $allowedHosts = $this->allowedHostsProvider->getAllowedHosts();
        $allowedOrigins = [];

        $origin = $request->headers->get('Origin');
        if ($origin !== null) {
            if ($this->allowedHostsProvider->isAllowedOrigin($origin, $request->getHttpHost())) {
                $allowedOrigins[] = $origin;
            } elseif (strtolower(rtrim($origin, '/')) !== strtolower($request->getSchemeAndHttpHost())) {
                $this->logger?->warning('MCP request rejected: Origin "{origin}" is not allowed. Add it to shopware.mcp.allowed_origins to accept it.', [
                    'origin' => $origin,
                    'host' => $request->getHttpHost(),
                ]);
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
