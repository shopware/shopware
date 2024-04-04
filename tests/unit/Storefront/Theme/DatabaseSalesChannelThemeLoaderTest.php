<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Theme\DatabaseSalesChannelThemeLoader;

/**
 * @internal
 */
#[CoversClass(DatabaseSalesChannelThemeLoader::class)]
class DatabaseSalesChannelThemeLoaderTest extends TestCase
{
    private Connection&MockObject $connection;

    private DatabaseSalesChannelThemeLoader $themeLoader;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->themeLoader = new DatabaseSalesChannelThemeLoader(
            $this->connection,
        );
    }

    public function testLoadWithDifferentSalesChannel(): void
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
    }

    public function testLoadMultiple(): void
    {
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
    }
}
