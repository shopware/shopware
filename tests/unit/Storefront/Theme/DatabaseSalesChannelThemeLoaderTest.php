<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Theme\DatabaseSalesChannelThemeLoader;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(DatabaseSalesChannelThemeLoader::class)]
class DatabaseSalesChannelThemeLoaderTest extends TestCase
{
    private Connection&MockObject $connection;

    private DatabaseSalesChannelThemeLoader $themeLoader;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->themeLoader = new DatabaseSalesChannelThemeLoader($this->connection);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<string> $expected
     */
    #[DataProvider('themeGraphProvider')]
    public function testLoad(array $rows, array $expected): void
    {
        $this->connection->expects($this->once())->method('fetchAllAssociative')->willReturn($rows);

        static::assertSame($expected, $this->themeLoader->load(Uuid::randomHex()));
    }

    public static function themeGraphProvider(): \Generator
    {
        yield 'no theme assigned to the sales channel' => [
            [self::row('storefront', 'Storefront')],
            [],
        ];

        yield 'only the base theme' => [
            [self::row('storefront', 'Storefront', assigned: true)],
            ['Storefront'],
        ];

        yield 'linear parent_theme_id chain' => [
            [
                self::row('t1', 'Extended thrice', parentThemeId: 't2', assigned: true),
                self::row('t2', 'Extended twice', parentThemeId: 't3'),
                self::row('t3', 'Extended once', parentThemeId: 't4'),
                self::row('t4', 'Extended', parentThemeId: 'storefront'),
                self::row('storefront', 'Storefront'),
            ],
            ['Extended thrice', 'Extended twice', 'Extended once', 'Extended', 'Storefront'],
        ];

        // addParentTheme() stored BasicTheme, ParentTheme is only reachable through configInheritance.
        yield 'multiple configInheritance parents with a stale parent_theme_id' => [
            [
                self::row('child', 'ChildTheme', parentThemeId: 'basic', assigned: true, configInheritance: ['@Storefront', '@BasicTheme', '@ParentTheme']),
                self::row('parent', 'ParentTheme', parentThemeId: 'basic', configInheritance: ['@Storefront', '@BasicTheme']),
                self::row('basic', 'BasicTheme', configInheritance: ['@Storefront']),
                self::row('storefront', 'Storefront'),
            ],
            ['ChildTheme', 'BasicTheme', 'ParentTheme', 'Storefront'],
        ];

        yield 'configInheritance is expanded transitively' => [
            [
                self::row('child', 'ChildTheme', assigned: true, configInheritance: ['@ParentTheme']),
                self::row('parent', 'ParentTheme', configInheritance: ['@BasicTheme']),
                self::row('basic', 'BasicTheme'),
            ],
            ['ChildTheme', 'ParentTheme', 'BasicTheme'],
        ];

        yield 'database copy without technical name uses its parent' => [
            [
                self::row('copy', null, parentThemeId: 'child', assigned: true),
                self::row('child', 'ChildTheme', configInheritance: ['@Storefront', '@BasicTheme']),
                self::row('basic', 'BasicTheme'),
                self::row('storefront', 'Storefront'),
            ],
            ['ChildTheme', 'BasicTheme', 'Storefront'],
        ];

        yield 'cyclic inheritance terminates' => [
            [
                self::row('a', 'A', parentThemeId: 'b', assigned: true, configInheritance: ['@B']),
                self::row('b', 'B', parentThemeId: 'a', configInheritance: ['@A']),
            ],
            ['A', 'B'],
        ];

        yield 'configInheritance naming an uninstalled theme is ignored' => [
            [self::row('child', 'ChildTheme', assigned: true, configInheritance: ['@NotInstalled'])],
            ['ChildTheme'],
        ];

        yield 'theme referencing itself is not duplicated' => [
            [self::row('child', 'ChildTheme', assigned: true, configInheritance: ['@ChildTheme'])],
            ['ChildTheme'],
        ];

        yield 'missing base_config' => [
            [self::row('child', 'ChildTheme', parentThemeId: 'storefront', assigned: true), self::row('storefront', 'Storefront')],
            ['ChildTheme', 'Storefront'],
        ];

        yield 'malformed base_config' => [
            [
                ['themeId' => 'child', 'technicalName' => 'ChildTheme', 'parentThemeId' => null, 'configInheritance' => 'not json', 'assigned' => 1],
            ],
            ['ChildTheme'],
        ];
    }

    public function testResultIsMemoisedPerSalesChannel(): void
    {
        $this->connection->expects($this->exactly(2))->method('fetchAllAssociative')->willReturn([
            self::row('storefront', 'Storefront', assigned: true),
        ]);

        $salesChannelId = Uuid::randomHex();
        static::assertSame(['Storefront'], $this->themeLoader->load($salesChannelId));
        static::assertSame(['Storefront'], $this->themeLoader->load($salesChannelId));

        static::assertSame(['Storefront'], $this->themeLoader->load(Uuid::randomHex()));
    }

    public function testEmptyResultIsNotMemoised(): void
    {
        $this->connection->expects($this->exactly(2))->method('fetchAllAssociative')->willReturn([]);

        $salesChannelId = Uuid::randomHex();
        static::assertSame([], $this->themeLoader->load($salesChannelId));
        static::assertSame([], $this->themeLoader->load($salesChannelId));
    }

    public function testResetClearsTheMemoisedResult(): void
    {
        $this->connection->expects($this->exactly(2))->method('fetchAllAssociative')->willReturn([
            self::row('storefront', 'Storefront', assigned: true),
        ]);

        $salesChannelId = Uuid::randomHex();
        static::assertSame(['Storefront'], $this->themeLoader->load($salesChannelId));

        $this->themeLoader->reset();

        static::assertSame(['Storefront'], $this->themeLoader->load($salesChannelId));
    }

    /**
     * @param list<string>|null $configInheritance
     *
     * @return array<string, mixed>
     */
    private static function row(
        string $themeId,
        ?string $technicalName,
        ?string $parentThemeId = null,
        bool $assigned = false,
        ?array $configInheritance = null,
    ): array {
        return [
            'themeId' => $themeId,
            'technicalName' => $technicalName,
            'parentThemeId' => $parentThemeId,
            'configInheritance' => $configInheritance === null ? null : json_encode($configInheritance, \JSON_THROW_ON_ERROR),
            'assigned' => $assigned ? 1 : 0,
        ];
    }
}
