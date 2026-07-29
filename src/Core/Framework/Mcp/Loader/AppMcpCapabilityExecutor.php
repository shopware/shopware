<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Loader;

use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\Hmac\RequestSigner;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 *
 * Executes app MCP capability calls (tools, prompts, resources). For external URLs, uses
 * HMAC-signed HTTP POST to the app's webhook URL. For internal paths (starting with '/'),
 * dispatches a Symfony subrequest so app scripts at /api/script/{path} can serve capability
 * logic without an external server.
 */
#[Package('framework')]
class AppMcpCapabilityExecutor
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Client $client,
        private readonly string $shopUrl,
        private readonly ShopIdProvider $shopIdProvider,
        private readonly int $timeout,
        private readonly LoggerInterface $logger,
        private readonly KernelInterface $kernel,
        private readonly RequestStack $requestStack,
        private readonly RouterInterface $router,
    ) {
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public function execute(string $capabilityName, ?string $appSecret, string $url, array $arguments, string $appVersion = '0.0.0'): string
    {
        if (str_starts_with($url, '/')) {
            return $this->executeSubRequest($capabilityName, $url, $arguments);
        }

        $payload = Json::encode([
            'tool' => $capabilityName,
            'arguments' => $arguments,
            'source' => [
                'url' => $this->shopUrl,
                'shopId' => $this->shopIdProvider->getShopId()->id,
                'appVersion' => $appVersion,
            ],
        ]);

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if ($appSecret !== null) {
            $headers[RequestSigner::SHOPWARE_SHOP_SIGNATURE] = (new RequestSigner())->signPayload($payload, $appSecret);
        }

        try {
            $response = $this->client->post($url, [
                'body' => $payload,
                'headers' => $headers,
                'timeout' => $this->timeout,
            ]);

            $body = $response->getBody()->getContents();

            $this->logger->debug('App MCP capability executed', [
                'capability' => $capabilityName,
                'url' => $url,
                'statusCode' => $response->getStatusCode(),
            ]);

            $decoded = json_decode($body, true);
            if (\is_array($decoded) && !\array_key_exists('success', $decoded)) {
                $this->logger->warning('App MCP capability response does not follow the response convention (missing "success" key)', [
                    'capability' => $capabilityName,
                    'url' => $url,
                ]);
            }

            return $body;
        } catch (\Throwable $e) {
            $this->logger->error('App MCP capability execution failed', [
                'capability' => $capabilityName,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return Json::encode([
                'success' => false,
                'error' => \sprintf('App capability "%s" execution failed: %s', $capabilityName, $e->getMessage()),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function executeSubRequest(string $capabilityName, string $url, array $arguments): string
    {
        $parent = $this->requestStack->getCurrentRequest();
        if ($parent === null) {
            return Json::encode(['success' => false, 'error' => 'No active request context']);
        }

        try {
            $route = $this->router->match($url);

            $server = [];
            if ($parent->headers->has('Authorization')) {
                $server['HTTP_AUTHORIZATION'] = $parent->headers->get('Authorization');
            }
            $server['CONTENT_TYPE'] = 'application/json';

            $body = Json::encode(['arguments' => $arguments]);
            $subRequest = Request::create($url, 'POST', [], [], [], $server, $body);
            $subRequest->attributes->add($route);

            if ($parent->attributes->get(PlatformRequest::ATTRIBUTE_OAUTH_PRE_AUTHENTICATED, false)) {
                $subRequest->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_ACCESS_TOKEN_ID, $parent->attributes->get(PlatformRequest::ATTRIBUTE_OAUTH_ACCESS_TOKEN_ID));
                $subRequest->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID, $parent->attributes->get(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID));
                $subRequest->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_PRE_AUTHENTICATED, true);
            }

            $response = $this->kernel->handle($subRequest, HttpKernelInterface::SUB_REQUEST);

            $this->logger->debug('App MCP capability executed via subrequest', [
                'capability' => $capabilityName,
                'url' => $url,
                'statusCode' => $response->getStatusCode(),
            ]);

            return $response->getContent() ?: json_encode(['success' => false, 'error' => 'Empty response'], \JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            $this->logger->error('App MCP capability subrequest execution failed', [
                'capability' => $capabilityName,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return Json::encode([
                'success' => false,
                'error' => \sprintf('App capability "%s" internal execution failed: %s', $capabilityName, $e->getMessage()),
            ]);
        }
    }
}
