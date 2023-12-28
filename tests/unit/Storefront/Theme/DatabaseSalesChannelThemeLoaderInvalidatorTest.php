<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Theme\DatabaseSalesChannelThemeLoader;
use Shopware\Storefront\Theme\DatabaseSalesChannelThemeLoaderInvalidator;
use Shopware\Storefront\Theme\Event\ThemeAssignedEvent;
use Shopware\Storefront\Theme\Event\ThemeConfigChangedEvent;
use Shopware\Storefront\Theme\Event\ThemeConfigResetEvent;

/**
 * @internal
 */
#[CoversClass(DatabaseSalesChannelThemeLoaderInvalidator::class)]
class DatabaseSalesChannelThemeLoaderInvalidatorTest extends TestCase
{
    private DatabaseSalesChannelThemeLoaderInvalidator $cachedSalesChannelThemeLoaderInvalidator;

    private MockedCacheInvalidator $cacheInvalidator;

    protected function setUp(): void
    {
        $this->cacheInvalidator = new MockedCacheInvalidator();
        $connectionMock = $this->createMock(Connection::class);
        $this->cachedSalesChannelThemeLoaderInvalidator = new DatabaseSalesChannelThemeLoaderInvalidator(
            $this->cacheInvalidator,
            new DatabaseSalesChannelThemeLoader($connectionMock)
        );
    }

    public function testGetSubscribedEvents(): void
    {
        static::assertEquals(
            [
                ThemeConfigChangedEvent::class => 'invalidate',
                ThemeAssignedEvent::class => 'invalidate',
                ThemeConfigResetEvent::class => 'invalidate',
            ],
            DatabaseSalesChannelThemeLoaderInvalidator::getSubscribedEvents()
        );
    }

    public function testInvalidate(): void
    {
        $expectedInvalidatedTags = [
            'sales-channel-themes',
        ];

        $this->cachedSalesChannelThemeLoaderInvalidator->invalidate();

        static::assertEquals(
            $expectedInvalidatedTags,
            $this->cacheInvalidator->getInvalidatedTags()
        );
    }
}
