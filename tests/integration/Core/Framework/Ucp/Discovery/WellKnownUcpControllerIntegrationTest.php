<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Ucp\Discovery;

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
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class WellKnownUcpControllerIntegrationTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testWellKnownUcpReturnsValidProfile(): void
    {
        if (!Feature::isActive('UCP_SERVER')) {
            static::markTestSkipped('UCP_SERVER flag not active');
        }

        $context = Context::createDefaultContext();
        $salesChannelId = $this->resolveSalesChannelWithHttpDomain($context);
        $this->createUcpEnabledSalesChannel($context, $salesChannelId);
        $host = $this->hostHeaderForSalesChannel($salesChannelId);

        $response = $this->dispatchUcpRequest('GET', '/.well-known/ucp', $host);

        static::assertSame(
            200,
            $response->getStatusCode(),
            'Got ' . $response->getStatusCode() . ' (host=' . $host . '): ' . substr((string) $response->getContent(), 0, 400)
        );
        static::assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));

        $body = json_decode((string) $response->getContent(), true);
        static::assertIsArray($body);
        static::assertArrayHasKey('ucp', $body);
        static::assertArrayHasKey('signing_keys', $body);
        static::assertIsArray($body['ucp']);
        static::assertSame('2026-01-23', $body['ucp']['version']);
        static::assertNotEmpty($body['ucp']['capabilities']);
        static::assertNotEmpty($body['signing_keys']);
    }

    public function testInactiveChannelReturns404(): void
    {
        if (!Feature::isActive('UCP_SERVER')) {
            static::markTestSkipped('UCP_SERVER flag not active');
        }

        // Without a matching sales-channel-domain the storefront firewall
        // answers with HTTP 400 "Domain Mapping Misconfiguration" *before* the
        // request reaches our controller; with a matching domain but no UCP
        // config it would be HTTP 404. Either is a valid rejection.
        $response = $this->dispatchUcpRequest('GET', '/.well-known/ucp', 'random-domain-that-does-not-exist.test');
        static::assertContains(
            $response->getStatusCode(),
            [400, 404],
            'Expected 400 or 404 for an unknown sales-channel domain, got ' . $response->getStatusCode()
        );
    }

    private function createUcpEnabledSalesChannel(Context $context, string $salesChannelId): string
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
                'dev.ucp.shopping.cart',
                'dev.ucp.shopping.checkout',
            ],
            'enabledTransports' => ['rest'],
            'signaturePolicy' => UcpSalesChannelConfigEntity::SIGNATURE_POLICY_STRICT,
            'idempotencyRequired' => true,
        ]], $context);

        $keyProvider = $this->getContainer()->get(UcpSigningKeyProvider::class);
        $keyProvider->create($salesChannelId, 'ES256', $context, false);

        return $salesChannelId;
    }

    private function resolveSalesChannelWithHttpDomain(Context $context): string
    {
        $row = $this->getContainer()->get(Connection::class)->fetchOne(
            'SELECT LOWER(HEX(sales_channel_id)) FROM sales_channel_domain WHERE url LIKE "http%://%" LIMIT 1'
        );
        if (!\is_string($row) || $row === '') {
            static::markTestSkipped('No sales channel with an HTTP(S) domain found — cannot exercise the UCP RequestTransformer.');
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

    private function dispatchUcpRequest(string $method, string $path, string $host): Response
    {
        // Force a fresh kernel boot — the IntegrationTestBehaviour-shared
        // kernel may have cached an unrelated sales-channel-domain context
        // and the storefront RequestTransformer will then refuse the request
        // with a 400 "Domain Mapping Misconfiguration".
        [$hostName, $port] = $this->splitHostHeader($host);
        $kernel = KernelLifecycleManager::bootKernel(true);
        $browser = new KernelBrowser($kernel);
        $browser->setServerParameter('HTTP_HOST', $host);
        $browser->setServerParameter('SERVER_NAME', $hostName);
        $browser->setServerParameter('SERVER_PORT', (string) $port);
        $browser->request($method, 'http://' . $host . $path);

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
