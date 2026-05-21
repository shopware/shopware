<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Ucp\Capability\Cart;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Entity\UcpSalesChannelConfigEntity;
use Shopware\Core\Framework\Ucp\Jwt\UcpSigningKeyProvider;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests the full POST /ucp/v1/carts → GET /ucp/v1/carts/{id} roundtrip
 * against a real Kernel + DB + product fixtures.
 *
 * @internal
 */
class CartRoundtripIntegrationTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const MOCK_PLATFORM_PROFILE_URL = 'http://localhost/mock-platform-profile.json';

    public function testCreateCartReadCartRoundtrip(): void
    {
        if (!Feature::isActive('UCP_SERVER')) {
            static::markTestSkipped('UCP_SERVER flag not active');
        }

        $context = Context::createDefaultContext();
        $salesChannelId = $this->resolveSalesChannelWithHttpDomain($context);
        $this->enableUcpForSalesChannel($context, $salesChannelId);
        $productId = $this->createTestProduct($context, $salesChannelId);
        $this->hostMockPlatformProfile();

        $response = $this->dispatchUcpRequest(
            'POST',
            '/ucp/v1/carts',
            $salesChannelId,
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_UCP_AGENT' => 'profile="' . self::MOCK_PLATFORM_PROFILE_URL . '"',
            ],
            json_encode(['line_items' => [['item' => ['id' => $productId], 'quantity' => 2]]], \JSON_THROW_ON_ERROR)
        );

        static::assertSame(201, $response->getStatusCode(), 'POST /ucp/v1/carts should return 201');

        $body = json_decode((string) $response->getContent(), true);
        static::assertIsArray($body);
        static::assertArrayHasKey('id', $body);
        static::assertNotEmpty($body['line_items']);
        static::assertSame(2, $body['line_items'][0]['quantity']);
        static::assertArrayHasKey('ucp', $body);
        static::assertIsArray($body['ucp']);
        static::assertSame('2026-01-23', $body['ucp']['version']);

        $cartId = (string) $body['id'];

        $readResponse = $this->dispatchUcpRequest(
            'GET',
            '/ucp/v1/carts/' . $cartId,
            $salesChannelId,
            [
                'HTTP_UCP_AGENT' => 'profile="' . self::MOCK_PLATFORM_PROFILE_URL . '"',
                'HTTP_SW_CONTEXT_TOKEN' => $cartId,
            ]
        );
        static::assertSame(200, $readResponse->getStatusCode());
        $readBody = json_decode((string) $readResponse->getContent(), true);
        static::assertIsArray($readBody);
        static::assertSame($cartId, $readBody['id']);
        static::assertCount(1, $readBody['line_items']);
    }

    public function testMissingUcpAgentHeaderRejects(): void
    {
        if (!Feature::isActive('UCP_SERVER')) {
            static::markTestSkipped('UCP_SERVER flag not active');
        }

        $context = Context::createDefaultContext();
        $salesChannelId = $this->resolveSalesChannelWithHttpDomain($context);
        $this->enableUcpForSalesChannel($context, $salesChannelId);

        $response = $this->dispatchUcpRequest(
            'POST',
            '/ucp/v1/carts',
            $salesChannelId,
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['line_items' => []], \JSON_THROW_ON_ERROR)
        );

        static::assertSame(400, $response->getStatusCode());
    }

    private function enableUcpForSalesChannel(Context $context, string $salesChannelId): void
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
            'enabledCapabilities' => ['dev.ucp.shopping.cart', 'dev.ucp.shopping.checkout'],
            'enabledTransports' => ['rest'],
            // log policy verifies but accepts; allows the test to exercise the
            // controller without forging a real RFC 9421 signature.
            'signaturePolicy' => UcpSalesChannelConfigEntity::SIGNATURE_POLICY_LOG,
            'idempotencyRequired' => false,
        ]], $context);

        $this->getContainer()->get(UcpSigningKeyProvider::class)->create($salesChannelId, 'ES256', $context, false);
    }

    private function createTestProduct(Context $context, string $salesChannelId): string
    {
        $productRepository = $this->getContainer()->get('product.repository');
        \assert($productRepository instanceof EntityRepository);
        $productId = Uuid::randomHex();
        $productRepository->create([[
            'id' => $productId,
            'productNumber' => 'UCP-TEST-' . substr($productId, 0, 8),
            'stock' => 10,
            'name' => 'UCP Test Product',
            'price' => [[
                'currencyId' => Defaults::CURRENCY,
                'gross' => 100,
                'net' => 84,
                'linked' => false,
            ]],
            'taxId' => $this->getContainer()->get(Connection::class)
                ->fetchOne('SELECT LOWER(HEX(id)) FROM tax LIMIT 1'),
            'visibilities' => [[
                'salesChannelId' => $salesChannelId,
                'visibility' => 30,
            ]],
        ]], $context);

        return $productId;
    }

    private function hostMockPlatformProfile(): void
    {
        $path = $this->getContainer()->getParameter('kernel.project_dir') . '/public/mock-platform-profile.json';
        if (is_file($path)) {
            return;
        }
        file_put_contents($path, json_encode([
            'ucp' => [
                'version' => '2026-01-23',
                'capabilities' => [
                    'dev.ucp.shopping.cart' => [['version' => '2026-01-23']],
                    'dev.ucp.shopping.checkout' => [['version' => '2026-01-23']],
                ],
            ],
            'signing_keys' => [],
        ], \JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string, string> $headers
     */
    private function dispatchUcpRequest(string $method, string $path, string $salesChannelId, array $headers, ?string $body = null): Response
    {
        // See WellKnownUcpControllerIntegrationTest::dispatchUcpRequest() — the
        // storefront RequestTransformer needs a full URL plus SERVER_NAME /
        // SERVER_PORT and a freshly booted kernel for sales-channel-domain
        // resolution to fire correctly.
        $host = $this->hostHeaderForSalesChannel($salesChannelId);
        [$hostName, $port] = $this->splitHostHeader($host);
        $kernel = KernelLifecycleManager::bootKernel(true);
        $browser = new KernelBrowser($kernel);
        $browser->setServerParameter('HTTP_HOST', $host);
        $browser->setServerParameter('SERVER_NAME', $hostName);
        $browser->setServerParameter('SERVER_PORT', (string) $port);
        $browser->request($method, 'http://' . $host . $path, [], [], $headers, $body);

        return $browser->getResponse();
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
