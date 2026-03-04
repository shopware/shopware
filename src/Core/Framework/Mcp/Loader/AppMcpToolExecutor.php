<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Loader;

use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\Hmac\RequestSigner;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 *
 * Executes app MCP tool calls via HMAC-signed HTTP POST to the app's webhook URL.
 */
#[Package('framework')]
class AppMcpToolExecutor
{
    public function __construct(
        private readonly Client $client,
        private readonly string $shopUrl,
        private readonly int $timeout,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public function execute(string $toolName, string $appSecret, string $url, array $arguments): string
    {
        $payload = json_encode([
            'tool' => $toolName,
            'arguments' => $arguments,
            'source' => [
                'url' => $this->shopUrl,
            ],
        ], \JSON_THROW_ON_ERROR);

        $signature = (new RequestSigner())->signPayload($payload, $appSecret);

        try {
            $response = $this->client->post($url, [
                'body' => $payload,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    RequestSigner::SHOPWARE_SHOP_SIGNATURE => $signature,
                ],
                'timeout' => $this->timeout,
            ]);

            $body = $response->getBody()->getContents();

            $this->logger?->info('App MCP tool executed', [
                'tool' => $toolName,
                'url' => $url,
                'statusCode' => $response->getStatusCode(),
            ]);

            return $body;
        } catch (\Throwable $e) {
            $this->logger?->error('App MCP tool execution failed', [
                'tool' => $toolName,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return json_encode([
                'success' => false,
                'error' => \sprintf('App tool "%s" execution failed: %s', $toolName, $e->getMessage()),
            ], \JSON_THROW_ON_ERROR);
        }
    }
}
