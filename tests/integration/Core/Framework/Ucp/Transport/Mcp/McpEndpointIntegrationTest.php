<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Ucp\Transport\Mcp;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Entity\UcpSalesChannelConfigEntity;
use Shopware\Core\Framework\Ucp\Jwt\UcpSigningKeyProvider;
use Shopware\Core\Framework\Ucp\Transport\Mcp\UcpMcpServerController;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class McpEndpointIntegrationTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const MOCK_PLATFORM_PROFILE_URL = 'http://localhost/mock-platform-profile.json';

    public function testInitializeReturnsServerInfo(): void
    {
        if (!Feature::isActive('UCP_SERVER')) {
            static::markTestSkipped('UCP_SERVER flag not active');
        }

        $context = Context::createDefaultContext();
        $salesChannelId = $this->resolveSalesChannelWithHttpDomain($context);
        $this->enableUcp($context, $salesChannelId);

        $response = $this->dispatchUcpJsonRpc([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => ['protocolVersion' => '2025-06-18'],
        ], $salesChannelId);

        static::assertSame(200, $response->getStatusCode());

        $body = json_decode((string) $response->getContent(), true);
        static::assertIsArray($body);
        static::assertSame('2.0', $body['jsonrpc']);
        static::assertSame(1, $body['id']);
        static::assertIsArray($body['result']);
        static::assertSame(UcpMcpServerController::PROTOCOL_VERSION, $body['result']['protocolVersion']);
        static::assertSame(UcpMcpServerController::SERVER_NAME, $body['result']['serverInfo']['name']);
    }

    public function testToolsListReturnsActiveTools(): void
    {
        if (!Feature::isActive('UCP_SERVER')) {
            static::markTestSkipped('UCP_SERVER flag not active');
        }

        $context = Context::createDefaultContext();
        $salesChannelId = $this->resolveSalesChannelWithHttpDomain($context);
        $this->enableUcp($context, $salesChannelId);

        $response = $this->dispatchUcpJsonRpc([
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/list',
        ], $salesChannelId);

        $body = json_decode((string) $response->getContent(), true);
        static::assertIsArray($body);
        static::assertIsArray($body['result']);
        static::assertIsArray($body['result']['tools']);

        $toolNames = array_column($body['result']['tools'], 'name');
        static::assertContains('create_cart', $toolNames);
        static::assertContains('search_catalog', $toolNames);

        foreach ($body['result']['tools'] as $tool) {
            static::assertIsArray($tool);
            static::assertArrayHasKey('name', $tool);
            static::assertArrayHasKey('description', $tool);
            static::assertArrayHasKey('inputSchema', $tool);
        }
    }

    public function testUnknownMethodReturnsJsonRpcError(): void
    {
        if (!Feature::isActive('UCP_SERVER')) {
            static::markTestSkipped('UCP_SERVER flag not active');
        }

        $context = Context::createDefaultContext();
        $salesChannelId = $this->resolveSalesChannelWithHttpDomain($context);
        $this->enableUcp($context, $salesChannelId);

        $response = $this->dispatchUcpJsonRpc([
            'jsonrpc' => '2.0',
            'id' => 99,
            'method' => 'does/not/exist',
        ], $salesChannelId);

        $body = json_decode((string) $response->getContent(), true);
        static::assertIsArray($body);
        static::assertIsArray($body['error']);
        static::assertSame(-32601, $body['error']['code']);
    }

    private function enableUcp(Context $context, string $salesChannelId): void
    {
        $configRepo = $this->getContainer()->get('ucp_sales_channel_config.repository');
        \assert($configRepo instanceof EntityRepository);
        $existing = $configRepo->searchIds(
            (new Criteria())
                ->addFilter(new EqualsFilter('salesChannelId', $salesChannelId))
                ->setLimit(1),
            $context
        )->firstId();
        $id = \is_string($existing) ? $existing : Uuid::randomHex();

        $configRepo->upsert([[
            'id' => $id,
            'salesChannelId' => $salesChannelId,
            'active' => true,
            'ucpVersion' => '2026-01-23',
            'profileUriStrategy' => UcpSalesChannelConfigEntity::STRATEGY_DOMAIN,
            'enabledCapabilities' => [
                'dev.ucp.shopping.catalog.search',
                'dev.ucp.shopping.cart',
                'dev.ucp.shopping.checkout',
            ],
            'enabledTransports' => ['rest', 'mcp'],
            // Integration tests exercise the JSON-RPC handler without forging
            // real RFC 9421 signatures — log policy still verifies, but
            // accepts the request so the handler is reached.
            'signaturePolicy' => UcpSalesChannelConfigEntity::SIGNATURE_POLICY_LOG,
            'idempotencyRequired' => false,
        ]], $context);

        $this->getContainer()->get(UcpSigningKeyProvider::class)->create($salesChannelId, 'ES256', $context, false);
    }

    private function resolveSalesChannelWithHttpDomain(Context $context): string
    {
        $row = $this->getContainer()->get(Connection::class)->fetchOne(
            'SELECT LOWER(HEX(sales_channel_id)) FROM sales_channel_domain WHERE url LIKE "http%://%" LIMIT 1'
        );
        if (!\is_string($row) || $row === '') {
            static::markTestSkipped('No sales channel with an HTTP(S) domain found.');
        }

        return $row;
    }

    private function hostHeaderForSalesChannel(string $salesChannelId): string
    {
        $row = $this->getContainer()->get(Connection::class)->fetchOne(
            'SELECT url FROM sales_channel_domain WHERE sales_channel_id = ? LIMIT 1',
            [Uuid::fromHexToBytes($salesChannelId)]
        );
        if (!\is_string($row)) {
            return 'localhost';
        }
        $parts = parse_url($row);
        if (!\is_array($parts) || !isset($parts['host'])) {
            return 'localhost';
        }

        return $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }

    /**
     * @param array<string, mixed> $body
     */
    private function dispatchUcpJsonRpc(array $body, string $salesChannelId): Response
    {
        // See WellKnownUcpControllerIntegrationTest::dispatchUcpRequest() —
        // KernelBrowser needs a full URL plus SERVER_NAME/SERVER_PORT and a
        // freshly booted kernel for the storefront RequestTransformer to
        // identify the sales-channel domain.
        $host = $this->hostHeaderForSalesChannel($salesChannelId);
        [$hostName, $port] = $this->splitHostHeader($host);
        $kernel = KernelLifecycleManager::bootKernel(true);
        $browser = new KernelBrowser($kernel);
        $browser->setServerParameter('HTTP_HOST', $host);
        $browser->setServerParameter('SERVER_NAME', $hostName);
        $browser->setServerParameter('SERVER_PORT', (string) $port);
        $browser->request(
            'POST',
            'http://' . $host . '/ucp/mcp',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_UCP_AGENT' => 'profile="' . self::MOCK_PLATFORM_PROFILE_URL . '"',
            ],
            json_encode($body, \JSON_THROW_ON_ERROR)
        );

        return $browser->getResponse();
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function splitHostHeader(string $host): array
    {
        if (str_contains($host, ':')) {
            [$h, $p] = explode(':', $host, 2);

            return [$h, (int) $p];
        }

        return [$host, 80];
    }
}
