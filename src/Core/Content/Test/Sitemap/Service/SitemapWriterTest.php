<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Sitemap\Service;

use League\Flysystem\FilesystemInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Sitemap\Service\SitemapNameGeneratorInterface;
use Shopware\Core\Content\Sitemap\Service\SitemapWriter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class SitemapWriterTest extends TestCase
{
    use IntegrationTestBehaviour;

    private $systemConfigService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->systemConfigService = $this->getContainer()->get(SystemConfigService::class);
    }

    public function testLocks(): void
    {
        $this->systemConfigService->set('core.sitemap.sitemapRefreshTime', 3600);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);

        $sitemapNameGenerator = $this->createMock(SitemapNameGeneratorInterface::class);
        $filesystem = $this->createMock(FilesystemInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $sitemapWriter = new SitemapWriter($sitemapNameGenerator, $filesystem, $logger, new ArrayAdapter(), $this->systemConfigService);

        // Check we can lock the Shop
        static::assertTrue($sitemapWriter->lock($salesChannelContext));

        // Check that we cannot lock the Shop again now
        static::assertFalse($sitemapWriter->lock($salesChannelContext));

        // Check we can unlock the shop
        static::assertTrue($sitemapWriter->unlock($salesChannelContext));

        // Check we can lock the shop again
        static::assertTrue($sitemapWriter->lock($salesChannelContext));

        // Finally unlock Shop again
        static::assertTrue($sitemapWriter->unlock($salesChannelContext));
    }
}
