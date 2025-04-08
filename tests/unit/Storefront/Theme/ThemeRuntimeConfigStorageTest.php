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
}
