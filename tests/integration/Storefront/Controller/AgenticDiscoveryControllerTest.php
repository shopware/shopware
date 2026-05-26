<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class AgenticDiscoveryControllerTest extends TestCase
{
    use IntegrationTestBehaviour;

    protected function setUp(): void
    {
        if (!Feature::isActive('AGENTIC_DISCOVERY')) {
            static::markTestSkipped('AGENTIC_DISCOVERY flag not active');
        }
    }

    public function testAgentsMdReturns404WithoutConfig(): void
    {
        $appUrl = $this->getAppUrl();

        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request('GET', $appUrl . '/agents.md');

        static::assertSame(404, $browser->getResponse()->getStatusCode());
    }

    public function testAgentsMdReturnsMarkdownWhenActive(): void
    {
        $this->seedDiscoveryConfig();
        $appUrl = $this->getAppUrl();

        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request('GET', $appUrl . '/agents.md');

        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode());
        static::assertStringStartsWith('text/markdown', (string) $response->headers->get('content-type'));
        $body = (string) $response->getContent();
        static::assertStringContainsString('# Agent Operating Manual', $body);
        static::assertStringContainsString('## Typical Agent Flow', $body);
        static::assertStringContainsString('## Rules', $body);
    }

    public function testLlmsTxtIncludesAgentsMdLink(): void
    {
        $this->seedDiscoveryConfig();
        $appUrl = $this->getAppUrl();

        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request('GET', $appUrl . '/llms.txt');

        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode());
        $body = (string) $response->getContent();
        static::assertStringContainsString('/agents.md', $body);
        static::assertStringContainsString('## For Agents & Developers', $body);
    }

    public function testLlmsFullTxtIncludesCatalogEndpoints(): void
    {
        $this->seedDiscoveryConfig();
        $appUrl = $this->getAppUrl();

        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request('GET', $appUrl . '/llms-full.txt');

        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode());
        $body = (string) $response->getContent();
        static::assertStringContainsString('## Catalog endpoints (Store API)', $body);
        static::assertStringContainsString('/store-api/product/', $body);
    }

    public function testAgenticSitemapReturnsXmlWithEntries(): void
    {
        $this->seedDiscoveryConfig();
        $appUrl = $this->getAppUrl();

        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request('GET', $appUrl . '/sitemap_agentic_discovery.xml');

        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode());
        static::assertStringStartsWith('application/xml', (string) $response->headers->get('content-type'));
        $body = (string) $response->getContent();
        static::assertStringContainsString('<?xml version="1.0"', $body);
        static::assertStringContainsString($appUrl . '/agents.md', $body);
        static::assertStringContainsString('<changefreq>weekly</changefreq>', $body);
    }

    public function testDocumentToggleReturns404(): void
    {
        $this->seedDiscoveryConfig(['exposeAgentsMd' => false]);
        $appUrl = $this->getAppUrl();

        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request('GET', $appUrl . '/agents.md');

        static::assertSame(404, $browser->getResponse()->getStatusCode());
    }

    public function testInactiveConfigReturns404(): void
    {
        $this->seedDiscoveryConfig(['active' => false]);
        $appUrl = $this->getAppUrl();

        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request('GET', $appUrl . '/llms.txt');

        static::assertSame(404, $browser->getResponse()->getStatusCode());
    }

    private function getAppUrl(): string
    {
        $appUrl = EnvironmentHelper::getVariable('APP_URL');
        static::assertIsString($appUrl);

        return $appUrl;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function seedDiscoveryConfig(array $overrides = []): void
    {
        /** @var EntityRepository $repository */
        $repository = static::getContainer()->get('agentic_discovery_sales_channel_config.repository');

        $appUrl = $this->getAppUrl();
        $connection = static::getContainer()->get(Connection::class);
        $row = $connection->fetchOne('SELECT LOWER(HEX(sales_channel_id)) FROM sales_channel_domain WHERE url = :url LIMIT 1', ['url' => $appUrl]);
        static::assertIsString($row);
        $salesChannelId = $row;

        $existing = $repository
            ->search((new Criteria())->addFilter(new EqualsFilter('salesChannelId', $salesChannelId)), Context::createDefaultContext())
            ->first();
        if ($existing !== null) {
            $repository->delete([['id' => $existing->getUniqueIdentifier()]], Context::createDefaultContext());
        }

        $payload = array_merge([
            'id' => Uuid::randomHex(),
            'salesChannelId' => $salesChannelId,
            'active' => true,
            'exposeAgentsMd' => true,
            'exposeLlmsTxt' => true,
            'exposeLlmsFullTxt' => true,
            'exposeAgenticSitemap' => true,
        ], $overrides);

        $repository->create([$payload], Context::createDefaultContext());
    }
}
