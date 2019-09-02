<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Sitemap\Provider;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Sitemap\Provider\CustomUrlProvider;
use Shopware\Core\Content\Sitemap\Service\ConfigHandler;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CustomUrlProviderTest extends TestCase
{
    public function testGetUrlsReturnsNoUrls(): void
    {
        $configHandlerStub = $this->createMock(ConfigHandler::class);
        $configHandlerStub->method('get')
            ->with(ConfigHandler::CUSTOM_URLS_KEY)
            ->willReturn([]);

        $customUrlProvider = $this->getCustomUrlProvider($configHandlerStub);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);

        static::assertSame([], $customUrlProvider->getUrls($salesChannelContext));
    }

    public function testGetUrlsReturnsAllUrlsForSalesChannel(): void
    {
        $salesChannelContext = $this->createMock(SalesChannelContext::class);

        $configHandlerStub = $this->createMock(ConfigHandler::class);
        $configHandlerStub->method('get')
            ->with(ConfigHandler::CUSTOM_URLS_KEY)
            ->willReturn([
                [
                    'url' => 'foo',
                    'lastMod' => new \DateTimeImmutable(),
                    'changeFreq' => 'weekly',
                    'priority' => 0.5,
                    'salesChannelId' => 2,
                ], [
                    'url' => 'bar',
                    'lastMod' => new \DateTimeImmutable(),
                    'changeFreq' => 'weekly',
                    'priority' => 0.5,
                    'salesChannelId' => $salesChannelContext->getSalesChannel()->getId(),
                ],
            ]);

        $customUrlProvider = $this->getCustomUrlProvider($configHandlerStub);

        static::assertCount(1, $customUrlProvider->getUrls($salesChannelContext));
    }

    public function testGetUrlsReturnsAllUrlsForSalesChannelIdNull(): void
    {
        $salesChannelContext = $this->createMock(SalesChannelContext::class);

        $configHandlerStub = $this->createMock(ConfigHandler::class);
        $configHandlerStub->method('get')
            ->with(ConfigHandler::CUSTOM_URLS_KEY)
            ->willReturn([
                [
                    'url' => 'foo',
                    'lastMod' => new \DateTimeImmutable(),
                    'changeFreq' => 'weekly',
                    'priority' => 0.5,
                    'salesChannelId' => 2,
                ], [
                    'url' => 'bar',
                    'lastMod' => new \DateTimeImmutable(),
                    'changeFreq' => 'weekly',
                    'priority' => 0.5,
                    'salesChannelId' => null,
                ], [
                    'url' => 'fooBar',
                    'lastMod' => new \DateTimeImmutable(),
                    'changeFreq' => 'weekly',
                    'priority' => 0.5,
                    'salesChannelId' => null,
                ],
            ]);

        $customUrlProvider = $this->getCustomUrlProvider($configHandlerStub);

        $urls = $customUrlProvider->getUrls($salesChannelContext);
        static::assertCount(2, $urls);
        static::assertSame('bar', $urls[0]->getLoc());
        static::assertSame('fooBar', $urls[1]->getLoc());
    }

    public function testGetUrlsReturnsNoUrlsWrongSalesChannelId(): void
    {
        $salesChannelContext = $this->createMock(SalesChannelContext::class);

        $configHandlerStub = $this->createMock(ConfigHandler::class);
        $configHandlerStub->method('get')
            ->with(ConfigHandler::CUSTOM_URLS_KEY)
            ->willReturn([
                [
                    'url' => 'foo',
                    'lastMod' => new \DateTimeImmutable(),
                    'changeFreq' => 'weekly',
                    'priority' => 0.5,
                    'salesChannelId' => 2,
                ],
            ]);

        $customUrlProvider = $this->getCustomUrlProvider($configHandlerStub);

        static::assertEmpty($customUrlProvider->getUrls($salesChannelContext));
    }

    private function getCustomUrlProvider(ConfigHandler $configHandlerStub): CustomUrlProvider
    {
        return new CustomUrlProvider($configHandlerStub);
    }
}
