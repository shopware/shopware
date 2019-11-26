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

    /** @var string */
    private $testOutputGZFilename;

    protected function setUp(): void
    {
        parent::setUp();
        $this->systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $contextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $this->salesChannelContext = $contextFactory->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

        // we need to temp. save the gz file
        // because the gzopen does only support i/o written files on disk
        $this->testOutputGZFilename = tmpfile() . '.gz';
    }

    public function tearDown(): void
    {
        parent::tearDown();

        if (file_exists($this->testOutputGZFilename)) {
            unlink($this->testOutputGZFilename);
        }
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

        /** @var string $gzMemoryContent */
        $gzMemoryContent = $filesystem->read($sitemap);

        // to extract the content of the gz sitemap it needs to be written to the disk temporarily
        file_put_contents($this->testOutputGZFilename, $gzMemoryContent);

        $content = $this->extractGZStream($this->testOutputGZFilename);

        $expected = sprintf('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://shopware.com</loc><lastmod>%s</lastmod><changefreq>weekly</changefreq><priority>0.5</priority></url></urlset>', (new \DateTime())->format('Y-m-d'));
        static::assertSame($expected, $content);
    }

    private function extractGZStream(string $gzPath): string
    {
        // Raising this value may increase performance
        $buffer_size = 4096; // read 4kb at a time

        /** @var resource $file */
        $file = gzopen($gzPath, 'rb');

        /** @var resource $destStream */
        $destStream = fopen('php://memory', 'wb');

        while (!gzeof($file)) {
            fwrite($destStream, gzread($file, $buffer_size));
        }

        // set back to pos 0
        rewind($destStream);

        /** @var string $plainContent */
        $plainContent = stream_get_contents($destStream);

        fclose($destStream);
        gzclose($file);

        return $plainContent;
    }
}
