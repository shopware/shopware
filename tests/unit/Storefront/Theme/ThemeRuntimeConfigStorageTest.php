<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\ThemeRuntimeConfig;
use Shopware\Storefront\Theme\ThemeRuntimeConfigStorage;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ThemeRuntimeConfigStorage::class)]
class ThemeRuntimeConfigStorageTest extends TestCase
{
    private Connection&MockObject $connection;

    private ThemeRuntimeConfigStorage $storage;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->storage = new ThemeRuntimeConfigStorage($this->connection);
    }

    /**
     * @param array<string, mixed>|false $record
     */
    #[DataProvider('configProvider')]
    public function testGetByName(string $themeId, string $themeName, array|false $record, ?ThemeRuntimeConfig $expectedObject): void
    {
        $this->connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->with(
                <<<'SQL'
                SELECT
                `theme_id`,
                `technical_name`,
                `resolved_config`,
                `view_inheritance`,
                `script_files`,
                `icon_sets`,
                `updated_at`
                FROM `theme_runtime_config`
                WHERE `technical_name` = :technicalName
            SQL,
                ['technicalName' => $themeName]
            )
            ->willReturn($record);

        $result = $this->storage->getByName($themeName);
        static::assertEquals($expectedObject, $result);
    }

    /**
     * @param array<string, mixed>|false $record
     */
    #[DataProvider('configProvider')]
    public function testGetById(string $themeId, string $themeName, array|false $record, ?ThemeRuntimeConfig $expectedObject): void
    {
        $this->connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->with(
                <<<'SQL'
                SELECT
                `theme_id`,
                `technical_name`,
                `resolved_config`,
                `view_inheritance`,
                `script_files`,
                `icon_sets`,
                `updated_at`
                FROM `theme_runtime_config`
                WHERE `theme_id` = :themeId
            SQL,
                ['themeId' => hex2bin($themeId)]
            )
            ->willReturn($record);

        $result = $this->storage->getById($themeId);
        static::assertEquals($expectedObject, $result);
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: array<string, mixed>|false, 3: ?ThemeRuntimeConfig}>
     */
    public static function configProvider(): iterable
    {
        yield 'no record found' => [
            '1234567890abcdef1234567890abcde1',
            'nonexistent-theme-name',
            false,
            null,
        ];

        yield 'record found' => [
            '1234567890abcdef1234567890abcdef',
            'test-theme-name',
            [
                'theme_id' => hex2bin('1234567890abcdef1234567890abcdef'),
                'technical_name' => 'test-theme-name',
                'resolved_config' => json_encode(['key' => 'value']),
                'view_inheritance' => json_encode(['parent-theme']),
                'script_files' => json_encode(['file1.js', 'file2.js']),
                'icon_sets' => json_encode(['iconSet1' => ['path' => 'path/to/iconSet1', 'namespace' => 'testTheme']]),
                'updated_at' => '2025-04-07 12:35:00.000',
            ],
            ThemeRuntimeConfig::fromArray([
                'themeId' => '1234567890abcdef1234567890abcdef',
                'technicalName' => 'test-theme-name',
                'resolvedConfig' => ['key' => 'value'],
                'viewInheritance' => ['parent-theme'],
                'scriptFiles' => ['file1.js', 'file2.js'],
                'iconSets' => ['iconSet1' => ['path' => 'path/to/iconSet1', 'namespace' => 'testTheme']],
                'updatedAt' => new \DateTimeImmutable('2025-04-07 12:35:00'),
            ]),
        ];
    }

    public function testSaveExecutesStatement(): void
    {
        $config = ThemeRuntimeConfig::fromArray([
            'themeId' => '1234567890abcdef1234567890abcdef',
            'technicalName' => 'test-theme',
            'resolvedConfig' => ['key' => 'value'],
            'viewInheritance' => ['parent-theme'],
            'scriptFiles' => ['file1.js', 'file2.js'],
            'iconSets' => ['iconSet1' => ['path' => 'path/to/iconSet1', 'namespace' => 'testTheme']],
            'updatedAt' => new \DateTimeImmutable('2023-01-01 00:00:00'),
        ]);

        $this->connection->expects($this->once())
            ->method('executeStatement');

        $this->storage->save($config);
    }

    public function testGetActiveThemeNames(): void
    {
        $fetchOutput = ['theme1', 'theme2'];
        $this->connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn($fetchOutput);

        $result = $this->storage->getActiveThemeNames();

        static::assertSame($fetchOutput, $result);
    }

    public function testGetCopiesIds(): void
    {
        $themeId = '1234567890abcdef1234567890abcdef';
        $expectedIds = ['11111111111111111111111111111111', '22222222222222222222222222222222'];

        $this->connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn($expectedIds);

        $result = $this->storage->getCopiesIds($themeId);

        static::assertSame($expectedIds, $result);
    }

    public function testGetChildThemeIds(): void
    {
        $parentThemeId = '1234567890abcdef1234567890abcdef';
        $childThemeIds = ['11111111111111111111111111111111', '22222222222222222222222222222222'];
        $grandChildThemeIds = ['33333333333333333333333333333333'];
        $emptyResult = [];

        $callCount = 0;
        $this->connection
            ->expects($this->exactly(3))
            ->method('fetchFirstColumn')
            ->willReturnCallback(function (string $sql, array $params) use (&$callCount, $parentThemeId, $childThemeIds, $grandChildThemeIds, $emptyResult) {
                $expectedSql = <<<'SQL'
                    SELECT LOWER(HEX(id)) as id FROM theme WHERE parent_theme_id IN (:parentIds)
                SQL;

                static::assertSame($expectedSql, $sql);

                switch ($callCount) {
                    case 0:
                        // First call: get children of parent theme
                        static::assertSame([hex2bin($parentThemeId)], $params['parentIds']);
                        ++$callCount;

                        return $childThemeIds;
                    case 1:
                        // Second call: get children of child themes
                        static::assertSame(
                            array_map(fn ($id) => hex2bin($id), $childThemeIds),
                            $params['parentIds']
                        );
                        ++$callCount;

                        return $grandChildThemeIds;
                    case 2:
                        // Third call: get children of grandchild themes (should return empty)
                        static::assertSame(
                            array_map(fn ($id) => hex2bin($id), $grandChildThemeIds),
                            $params['parentIds']
                        );
                        ++$callCount;

                        return $emptyResult;
                    default:
                        throw new \RuntimeException('Unexpected call count');
                }
            });

        $result = $this->storage->getChildThemeIds($parentThemeId);

        // Verify that all child and grandchild theme IDs are included in the result
        static::assertSame([...$childThemeIds, ...$grandChildThemeIds], $result);
    }

    public function testGetThemeTechnicalName(): void
    {
        $themeId = '1234567890abcdef1234567890abcdef';
        $themeData = [
            'themeName' => 'test-theme',
            'parentThemeName' => 'parent-theme',
        ];

        $this->connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn($themeData);

        $result = $this->storage->getThemeTechnicalName($themeId);

        static::assertSame($themeData['themeName'], $result);
    }

    public function testGetThemeTechnicalNameReturnsParentWhenNull(): void
    {
        $themeId = '1234567890abcdef1234567890abcdef';
        $themeData = [
            'themeName' => null,
            'parentThemeName' => 'parent-theme',
        ];

        $this->connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn($themeData);

        $result = $this->storage->getThemeTechnicalName($themeId);

        static::assertSame($themeData['parentThemeName'], $result);
    }

    public function testGetThemeTechnicalNameWithFalse(): void
    {
        $themeId = '1234567890abcdef1234567890abcdef';

        $this->connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn(false);

        $result = $this->storage->getThemeTechnicalName($themeId);

        static::assertNull($result);
    }

    public function testGetThemeIdByTechnicalName(): void
    {
        $technicalName = 'test-theme';
        $themeId = '1234567890abcdef1234567890abcdef';

        $this->connection
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn($themeId);

        $result = $this->storage->getThemeIdByTechnicalName($technicalName);

        static::assertSame($themeId, $result);
    }

    public function testGetThemeIdByTechnicalNameNotFound(): void
    {
        $technicalName = 'nonexistent-theme';

        $this->connection
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn(false);

        $result = $this->storage->getThemeIdByTechnicalName($technicalName);

        static::assertNull($result);
    }
}
