<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Sitemap\Service;

use League\Flysystem\DirectoryListing;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Sitemap\Service\SitemapLister;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Generator;
use Symfony\Component\Asset\Package;
use Symfony\Component\Clock\NativeClock;

/**
 * @internal
 */
#[\Shopware\Core\Framework\Log\Package('discovery')]
#[CoversClass(SitemapLister::class)]
class SitemapListerTest extends TestCase
{
    public function testListsFilesWithoutDomainId(): void
    {
        $context = Generator::generateSalesChannelContext();

        $filesystem = static::createStub(FilesystemOperator::class);
        $filesystem->method('listContents')->willReturn(new DirectoryListing([
            new FileAttributes('sitemap/salesChannel-' . $context->getSalesChannelId() . '-' . $context->getLanguageId() . '/' . $context->getSalesChannelId(), 0, null, null, null),
        ]));

        $package = static::createStub(Package::class);
        $package->method('getUrl')->willReturnCallback(static function (string $path) {
            return $path;
        });

        $sitemapLister = new SitemapLister($filesystem, $package, new NativeClock());

        $sitemaps = $sitemapLister->getSitemaps($context);

        static::assertCount(1, $sitemaps);
    }

    public function testSitemapWithMultipleDomainsUseCorrectDomains(): void
    {
        $context = Generator::generateSalesChannelContext();

        $domains = new SalesChannelDomainCollection();

        $defaultDomainUrl = 'https://default-sitemap.de';
        $domainUrl = 'https://test-sitemap.de';

        $defaultDomainId = Uuid::randomHex();
        $defaultDomain = new SalesChannelDomainEntity();
        $defaultDomain->setId($defaultDomainId);
        $defaultDomain->setUrl($defaultDomainUrl);
        $defaultDomain->setLanguageId($context->getLanguageId());

        $domains->add($defaultDomain);

        $domainId = Uuid::randomHex();
        $domain = new SalesChannelDomainEntity();
        $domain->setId($domainId);
        $domain->setUrl($domainUrl);
        $domain->setLanguageId($context->getLanguageId());

        $domains->add($domain);

        $context->getSalesChannel()->setDomains($domains);

        $filesystem = static::createStub(FilesystemOperator::class);
        $filesystem->method('listContents')->willReturn(new DirectoryListing([
            new FileAttributes('sitemap/salesChannel-' . $context->getSalesChannelId() . '-' . $context->getLanguageId() . '/' . $context->getSalesChannelId() . '-' . $defaultDomainId, 0, null, null, null),
            new FileAttributes('sitemap/salesChannel-' . $context->getSalesChannelId() . '-' . $context->getLanguageId() . '/' . $context->getSalesChannelId() . '-' . $domainId, 0, null, null, null),
        ]));

        $package = static::createStub(Package::class);
        $package->method('getUrl')->willReturnCallback(static function (string $path) {
            return $path;
        });

        $sitemapLister = new SitemapLister($filesystem, $package, new NativeClock());

        $sitemaps = $sitemapLister->getSitemaps($context);

        static::assertCount(2, $sitemaps);
        static::assertStringStartsWith($defaultDomainUrl, $sitemaps[0]->getFilename());
        static::assertStringStartsWith($domainUrl, $sitemaps[1]->getFilename());
    }

    public function testHeadlessSalesChannelListsFilesViaAssetPackage(): void
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());
        $salesChannel->setTypeId(Defaults::SALES_CHANNEL_TYPE_API);
        $salesChannel->setLanguageId(Defaults::LANGUAGE_SYSTEM);

        $context = Generator::generateSalesChannelContext(salesChannel: $salesChannel);

        $domainId = Uuid::randomHex();
        $domain = new SalesChannelDomainEntity();
        $domain->setId($domainId);
        $domain->setUrl('https://frontend.example');
        $domain->setLanguageId($context->getLanguageId());
        $domain->setIsExternalStorefront(true);

        $context->getSalesChannel()->setDomains(new SalesChannelDomainCollection([$domain]));

        $path = 'sitemap/salesChannel-' . $context->getSalesChannelId() . '-' . $context->getLanguageId() . '/' . $context->getSalesChannelId() . '-' . $domainId . '-sitemap-frontend-example-1.xml.gz';

        $filesystem = static::createStub(FilesystemOperator::class);
        $filesystem->method('listContents')->willReturn(new DirectoryListing([
            new FileAttributes($path, 0, null, null, null),
        ]));

        $package = static::createStub(Package::class);
        $package->method('getUrl')->willReturnCallback(static function (string $path) {
            return 'https://shop.example/' . $path;
        });

        $sitemapLister = new SitemapLister($filesystem, $package, new NativeClock());

        $sitemaps = $sitemapLister->getSitemaps($context);

        static::assertCount(1, $sitemaps);
        // the external storefront does not serve the files, so the asset host is used even though the domain matches
        static::assertSame('https://shop.example/' . $path, $sitemaps[0]->getFilename());
    }
}
