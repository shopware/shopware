<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Theme\DatabaseSalesChannelThemeLoader;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @internal
 */
#[CoversClass(DatabaseSalesChannelThemeLoader::class)]
class DatabaseSalesChannelThemeLoaderTest extends TestCase
{
    /**
     * @var Connection&MockObject
     */
    private Connection $connection;

    private DatabaseSalesChannelThemeLoader $themeLoader;

    private CacheInterface&MockObject $cache;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->themeLoader = new DatabaseSalesChannelThemeLoader(
            $this->connection,
            null
        );
    }

    public function testLoad(): void
    {
        $expectedDB = [
            'themeName' => 'Storefront',
            'parentThemeName' => null,
            'themeId' => Uuid::randomHex(),
        ];

        $expectedTheme = [
            'Storefront',
        ];

        $this->connection->expects(static::exactly(2))->method('fetchAssociative')->willReturnOnConsecutiveCalls($expectedDB, []);

        $salesChannelId = Uuid::randomHex();

        $actualTheme = $this->themeLoader->load($salesChannelId);
        static::assertEquals($expectedTheme, $actualTheme);

        $otherSalesChannelId = Uuid::randomHex();
        $secondAttempt = $this->themeLoader->load($otherSalesChannelId);
        static::assertEquals([], $secondAttempt);

        $themePropertyReflection = new \ReflectionProperty(DatabaseSalesChannelThemeLoader::class, 'themes');
        $themePropertyReflection->setAccessible(true);
        $themes = $themePropertyReflection->getValue($this->themeLoader);

        static::assertSame([
            $salesChannelId => $expectedTheme,
            $otherSalesChannelId => [],
        ], $themes);
    }

    public function testLoadPropertyCached(): void
    {
        $expectedTheme = [
            'Storefront',
        ];
        $salesChannelId = Uuid::randomHex();

        $themePropertyReflection = new \ReflectionProperty(DatabaseSalesChannelThemeLoader::class, 'themes');
        $themePropertyReflection->setAccessible(true);
        $themePropertyReflection->setValue($this->themeLoader, [
            $salesChannelId => $expectedTheme,
        ]);

        $actualTheme = $this->themeLoader->load($salesChannelId);
        static::assertEquals($expectedTheme, $actualTheme);
    }

    public function testLoadCached(): void
    {
        $cachedThemeLoader = new DatabaseSalesChannelThemeLoader(
            $this->connection,
            $this->cache
        );

        $expectedTheme = [
            'Storefront',
        ];
        $salesChannelId = Uuid::randomHex();

        $this->cache->expects(static::once())->method('get')->willReturn(CacheValueCompressor::compress($expectedTheme));

        $this->connection->expects(static::never())->method('fetchAssociative');

        $actualTheme = $cachedThemeLoader->load($salesChannelId);
        static::assertEquals($expectedTheme, $actualTheme);
    }

    public function testReset(): void
    {
        $expectedDB = [
            'themeName' => 'Storefront',
            'parentThemeName' => null,
            'themeId' => Uuid::randomHex(),
        ];

        $expectedTheme = [
            'Storefront',
        ];

        $this->connection->expects(static::exactly(1))->method('fetchAssociative')->willReturn($expectedDB);

        $salesChannelId = Uuid::randomHex();

        $this->themeLoader->load($salesChannelId);
        $this->themeLoader->reset();
        $themePropertyReflection = new \ReflectionProperty(DatabaseSalesChannelThemeLoader::class, 'themes');
        $themePropertyReflection->setAccessible(true);
        $actualThemes = $themePropertyReflection->getValue($this->themeLoader);

        static::assertEquals([], $actualThemes);
    }

    public function testLoadMultiple(): void
    {
        $this->themeLoader->reset();

        $expectedDB1 = [
            'themeName' => 'Extended thrice',
            'parentThemeName' => 'Extended twice',
            'themeId' => Uuid::randomHex(),
            'grandParentThemeId' => Uuid::randomHex(),
        ];

        $expectedDB2 = [
            'themeName' => 'Extended once',
            'parentThemeName' => 'Extended',
            'grandParentThemeId' => Uuid::randomHex(),
        ];

        $expectedDB3 = [
            'themeName' => 'Storefront',
            'parentThemeName' => null,
            'grandParentThemeId' => null,
        ];

        $expectedTheme = [
            'Extended thrice',
            'Extended twice',
            'Extended once',
            'Extended',
            'Storefront',
        ];

        $this->connection->expects(static::exactly(4))->method('fetchAssociative')->willReturnOnConsecutiveCalls($expectedDB1, $expectedDB2, $expectedDB3, []);
        $salesChannelId = Uuid::randomHex();

        $actualTheme = $this->themeLoader->load($salesChannelId);
        static::assertEquals($expectedTheme, $actualTheme);

        $otherSalesChannelId = Uuid::randomHex();
        $secondAttempt = $this->themeLoader->load($otherSalesChannelId);
        static::assertEquals([], $secondAttempt);

        $themePropertyReflection = new \ReflectionProperty(DatabaseSalesChannelThemeLoader::class, 'themes');
        $themePropertyReflection->setAccessible(true);
        $themes = $themePropertyReflection->getValue($this->themeLoader);

        static::assertSame([
            $salesChannelId => $expectedTheme,
            $otherSalesChannelId => [],
        ], $themes);
    }
}
