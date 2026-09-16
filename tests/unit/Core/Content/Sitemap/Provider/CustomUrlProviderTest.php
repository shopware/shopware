<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Sitemap\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Sitemap\Provider\CustomUrlProvider;
use Shopware\Core\Content\Sitemap\Service\ConfigHandler;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CustomUrlProvider::class)]
class CustomUrlProviderTest extends TestCase
{
    public function testGetUrlsReturnsNoUrls(): void
    {
        $configHandler = $this->createMock(ConfigHandler::class);
        $configHandler->expects($this->once())->method('get')->willReturnCallback(
            static function (string $key): array {
                static::assertSame(ConfigHandler::CUSTOM_URLS_KEY, $key);

                return [];
            }
        );

        $customUrlProvider = $this->getCustomUrlProvider($configHandler);

        $salesChannelContext = static::createStub(SalesChannelContext::class);

        static::assertSame([], $customUrlProvider->getUrls($salesChannelContext, 100)->getUrls());
    }

    public function testGetUrlsReturnsAllUrlsForSalesChannel(): void
    {
        $salesChannelContext = static::createStub(SalesChannelContext::class);

        $configHandler = $this->createMock(ConfigHandler::class);
        $configHandler->expects($this->once())->method('get')->willReturnCallback(
            static function (string $key) use ($salesChannelContext): array {
                static::assertSame(ConfigHandler::CUSTOM_URLS_KEY, $key);

                return [
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
                        'salesChannelId' => $salesChannelContext->getSalesChannelId(),
                    ],
                ];
            }
        );

        $customUrlProvider = $this->getCustomUrlProvider($configHandler);

        static::assertCount(1, $customUrlProvider->getUrls($salesChannelContext, 100)->getUrls());
    }

    public function testGetUrlsReturnsAllUrlsForSalesChannelIdNull(): void
    {
        $salesChannelContext = static::createStub(SalesChannelContext::class);

        $configHandler = $this->createMock(ConfigHandler::class);
        $configHandler->expects($this->once())->method('get')->willReturnCallback(
            static function (string $key): array {
                static::assertSame(ConfigHandler::CUSTOM_URLS_KEY, $key);

                return [
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
                ];
            }
        );

        $customUrlProvider = $this->getCustomUrlProvider($configHandler);

        $urls = $customUrlProvider->getUrls($salesChannelContext, 100)->getUrls();

        [$firstUrl, $secondUrl] = $urls;
        static::assertCount(2, $urls);
        static::assertSame('bar', $firstUrl->getLoc());
        static::assertSame('fooBar', $secondUrl->getLoc());
    }

    public function testGetUrlsReturnsNoUrlsWrongSalesChannelId(): void
    {
        $salesChannelContext = static::createStub(SalesChannelContext::class);

        $configHandler = $this->createMock(ConfigHandler::class);
        $configHandler->expects($this->once())->method('get')->willReturnCallback(
            static function (string $key): array {
                static::assertSame(ConfigHandler::CUSTOM_URLS_KEY, $key);

                return [
                    [
                        'url' => 'foo',
                        'lastMod' => new \DateTimeImmutable(),
                        'changeFreq' => 'weekly',
                        'priority' => 0.5,
                        'salesChannelId' => 2,
                    ],
                ];
            }
        );

        $customUrlProvider = $this->getCustomUrlProvider($configHandler);

        static::assertEmpty($customUrlProvider->getUrls($salesChannelContext, 100)->getUrls());
    }

    private function getCustomUrlProvider(ConfigHandler $configHandler): CustomUrlProvider
    {
        return new CustomUrlProvider($configHandler);
    }
}
