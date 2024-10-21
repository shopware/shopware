<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Sitemap\Service;

use League\Flysystem\DirectoryListing;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Sitemap\Service\SitemapLister;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\Test\Generator;
use Symfony\Component\Asset\Package;

/**
 * @internal
 */
#[CoversClass(SitemapLister::class)]
class SitemapListerTest extends TestCase
{
    public function testListsFilesWithoutDomainId(): void
    {
        $context = Generator::createSalesChannelContext();

        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->method('listContents')->willReturn(new DirectoryListing([
            new FileAttributes('sitemap/salesChannel-' . $context->getSalesChannel()->getId() . '-' . $context->getLanguageId() . '/' . $context->getSalesChannelId(), 0, null, null, null),
        ]));

        $package = $this->createMock(Package::class);
        $package->method('getUrl')->willReturnCallback(function (string $path) {
            return $path;
        });

        $sitemapLister = new SitemapLister($filesystem, $package);

        $sitemaps = $sitemapLister->getSitemaps($context);

        static::assertCount(1, $sitemaps);
    }

    public function testSitemapWithMultipleDomainsUseCorrectDomains(): void
    {
        $context = Generator::createSalesChannelContext();

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

        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->method('listContents')->willReturn(new DirectoryListing([
            new FileAttributes('sitemap/salesChannel-' . $context->getSalesChannel()->getId() . '-' . $context->getLanguageId() . '/' . $context->getSalesChannelId() . '-' . $defaultDomainId, 0, null, null, null),
            new FileAttributes('sitemap/salesChannel-' . $context->getSalesChannel()->getId() . '-' . $context->getLanguageId() . '/' . $context->getSalesChannelId() . '-' . $domainId, 0, null, null, null),
        ]));

        $package = $this->createMock(Package::class);
        $package->method('getUrl')->willReturnCallback(function (string $path) {
            return $path;
        });

        $sitemapLister = new SitemapLister($filesystem, $package);

        $sitemaps = $sitemapLister->getSitemaps($context);

        static::assertCount(2, $sitemaps);
        static::assertStringStartsWith($defaultDomainUrl, $sitemaps[0]->getFilename());
        static::assertStringStartsWith($domainUrl, $sitemaps[1]->getFilename());
    }
}
