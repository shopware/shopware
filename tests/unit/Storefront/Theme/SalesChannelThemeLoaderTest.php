<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Storefront\Theme\SalesChannelThemeLoader;

/**
 * @internal
 */
#[CoversClass(SalesChannelThemeLoader::class)]
#[DisabledFeatures(['v6.7.0.0'])]
class SalesChannelThemeLoaderTest extends TestCase
{
    /**
     * @var Connection&MockObject
     */
    private Connection $connection;

    private SalesChannelThemeLoader $themeLoader;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->themeLoader = new SalesChannelThemeLoader($this->connection);
    }

    public function testLoad(): void
    {
        $expectedTheme = [
            'themeName' => 'Storefront',
            'parentThemeName' => null,
            'themeId' => Uuid::randomHex(),
        ];

        $this->connection->expects(static::exactly(2))->method('fetchAssociative')->willReturnOnConsecutiveCalls($expectedTheme, []);
        $salesChannelId = Uuid::randomHex();

        $actualTheme = $this->themeLoader->load($salesChannelId);
        static::assertEquals($expectedTheme, $actualTheme);

        $otherSalesChannelId = Uuid::randomHex();
        $secondAttempt = $this->themeLoader->load($otherSalesChannelId);
        static::assertEquals([], $secondAttempt);

        $themePropertyReflection = new \ReflectionProperty(SalesChannelThemeLoader::class, 'themes');
        $themePropertyReflection->setAccessible(true);
        $themes = $themePropertyReflection->getValue($this->themeLoader);

        static::assertSame([
            $salesChannelId => $expectedTheme,
            $otherSalesChannelId => [],
        ], $themes);
    }

    public function testLoadCached(): void
    {
        $expectedTheme = [
            'themeName' => 'Storefront',
            'parentThemeName' => null,
            'themeId' => Uuid::randomHex(),
        ];
        $salesChannelId = Uuid::randomHex();

        $themePropertyReflection = new \ReflectionProperty(SalesChannelThemeLoader::class, 'themes');
        $themePropertyReflection->setAccessible(true);
        $themePropertyReflection->setValue($this->themeLoader, [
            $salesChannelId => $expectedTheme,
        ]);

        $actualTheme = $this->themeLoader->load($salesChannelId);
        static::assertEquals($expectedTheme, $actualTheme);
    }

    public function testReset(): void
    {
        $expectedTheme = [
            'themeName' => 'Storefront',
            'parentThemeName' => null,
            'themeId' => Uuid::randomHex(),
        ];
        $this->connection->expects(static::exactly(1))->method('fetchAssociative')->willReturn($expectedTheme);

        $salesChannelId = Uuid::randomHex();

        $this->themeLoader->load($salesChannelId);

        $this->themeLoader->reset();

        $themePropertyReflection = new \ReflectionProperty(SalesChannelThemeLoader::class, 'themes');
        $themePropertyReflection->setAccessible(true);
        $actualThemes = $themePropertyReflection->getValue($this->themeLoader);

        static::assertEquals([], $actualThemes);
    }
}
