<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Sitemap\Service;

use League\Flysystem\Directory;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Sitemap\Service\SitemapWriter;
use Shopware\Core\Content\Sitemap\Struct\Url;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class SitemapWriterTest extends TestCase
{
    use IntegrationTestBehaviour;

    private $systemConfigService;

    /**
     * @var SalesChannelContext
     */
    private $salesChannelContext;

    protected function setUp(): void
    {
        parent::setUp();
        $this->systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $contextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $this->salesChannelContext = $contextFactory->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);
    }

    public function testSitemapIsCreated(): void
    {
        $this->systemConfigService->set('core.sitemap.sitemapRefreshTime', 3600);

        $filesystem = $this->getPublicFilesystem();

        $sitemapWriter = new SitemapWriter($filesystem);

        $url = new Url();

        $url->setLoc('https://shopware.com');
        $url->setLastmod(new \DateTime());
        $url->setChangefreq('weekly');
        $url->setResource(CategoryEntity::class);
        $url->setIdentifier(Uuid::randomHex());

        $fileHandle = $sitemapWriter->createFile('test.gz');
        static::assertIsResource($fileHandle);

        $sitemapWriter->writeUrlsToFile([$url], $fileHandle);
        $sitemapWriter->finishFile($fileHandle);
        $sitemapWriter->moveFile('test.gz', $this->salesChannelContext);

        /** @var Directory $directory */
        $directory = $filesystem->get('sitemap/');

        [$sitemapDir] = $directory->getContents();

        $salesChannelId = $this->salesChannelContext->getSalesChannel()->getId();

        $domainId = $this->salesChannelContext->getSalesChannel()->getLanguageId();
        $expectedPath = 'sitemap/salesChannel-' . $salesChannelId . '-' . $domainId;

        static::assertSame($expectedPath, $sitemapDir['path']);

        $sitemap = $expectedPath . '/test.gz';
        static::assertTrue($filesystem->has($sitemap));
    }
}
