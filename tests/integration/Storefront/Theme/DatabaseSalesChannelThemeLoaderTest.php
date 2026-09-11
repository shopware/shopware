<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Theme;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Theme\DatabaseSalesChannelThemeLoader;

/**
 * @internal
 */
#[Package('discovery')]
class DatabaseSalesChannelThemeLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private DatabaseSalesChannelThemeLoader $themeLoader;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->themeLoader = static::getContainer()->get(DatabaseSalesChannelThemeLoader::class);
        $this->themeLoader->reset();
    }

    public function testUsedThemesContainEveryConfigInheritanceParent(): void
    {
        $basicId = $this->createTheme('BasicTheme', ['@Storefront']);
        $this->createTheme('ParentTheme', ['@Storefront', '@BasicTheme'], $basicId);

        // addParentTheme() stored BasicTheme, ParentTheme is only reachable through configInheritance.
        $childId = $this->createTheme('ChildTheme', ['@Storefront', '@BasicTheme', '@ParentTheme'], $basicId);

        $this->assignThemeToSalesChannel($childId, TestDefaults::SALES_CHANNEL);

        static::assertSame(
            ['ChildTheme', 'BasicTheme', 'ParentTheme', 'Storefront'],
            $this->themeLoader->load(TestDefaults::SALES_CHANNEL)
        );
    }

    public function testConfigInheritanceIsExpandedTransitively(): void
    {
        $basicId = $this->createTheme('BasicTheme', ['@Storefront']);
        $this->createTheme('ParentTheme', ['@Storefront', '@BasicTheme'], $basicId);
        $childId = $this->createTheme('ChildTheme', ['@ParentTheme']);

        $this->assignThemeToSalesChannel($childId, TestDefaults::SALES_CHANNEL);

        static::assertSame(
            ['ChildTheme', 'ParentTheme', 'BasicTheme', 'Storefront'],
            $this->themeLoader->load(TestDefaults::SALES_CHANNEL)
        );
    }

    public function testThemeCopyWithoutTechnicalNameResolvesThroughItsParent(): void
    {
        $basicId = $this->createTheme('BasicTheme', ['@Storefront']);
        $childId = $this->createTheme('ChildTheme', ['@Storefront', '@BasicTheme'], $basicId);
        $copyId = $this->createTheme(null, null, $childId);

        $this->assignThemeToSalesChannel($copyId, TestDefaults::SALES_CHANNEL);

        $usedThemes = $this->themeLoader->load(TestDefaults::SALES_CHANNEL);

        static::assertSame('ChildTheme', $usedThemes[0]);
        static::assertSame(['ChildTheme', 'BasicTheme', 'Storefront'], $usedThemes);
    }

    /**
     * @param list<string>|null $configInheritance
     */
    private function createTheme(?string $technicalName, ?array $configInheritance = null, ?string $parentThemeId = null): string
    {
        $id = Uuid::randomHex();

        $this->connection->insert('theme', [
            'id' => Uuid::fromHexToBytes($id),
            'technical_name' => $technicalName,
            'name' => $technicalName ?? 'Theme copy',
            'author' => 'test',
            'active' => 1,
            'base_config' => $configInheritance === null
                ? null
                : json_encode(['configInheritance' => $configInheritance], \JSON_THROW_ON_ERROR),
            'parent_theme_id' => $parentThemeId !== null ? Uuid::fromHexToBytes($parentThemeId) : null,
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s.v'),
        ]);

        return $id;
    }

    private function assignThemeToSalesChannel(string $themeId, string $salesChannelId): void
    {
        $this->connection->executeStatement(
            'REPLACE INTO theme_sales_channel (theme_id, sales_channel_id) VALUES (:themeId, :salesChannelId)',
            [
                'themeId' => Uuid::fromHexToBytes($themeId),
                'salesChannelId' => Uuid::fromHexToBytes($salesChannelId),
            ]
        );
    }
}
