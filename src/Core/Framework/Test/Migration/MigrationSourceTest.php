<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Migration;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Migration\MigrationSource;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

class MigrationSourceTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @dataProvider provideCoreRegexDataV6_3
     */
    public function testCoreRegexV63(string $subject, bool $shoulMatch): void
    {
        $pattern = $this->getContainer()->get('Shopware\Core\Framework\Migration\MigrationSource.core.V6_3')->getNamespacePattern();

        static::assertSame($shoulMatch, (bool) preg_match("/$pattern/", $subject, $subject));
    }

    /**
     * @dataProvider provideUnitTestData
     */
    public function testUnitRegex(string $subject, bool $shouldMatch): void
    {
        $source = new MigrationSource('tmp', [
            __DIR__ . '/1' => 'My\Test\Namespace',
            __DIR__ . '/2' => 'My\Test\Namespace2',
            __DIR__ . '/3' => 'My\Other\Namespace',
        ]);

        $pattern = $source->getNamespacePattern();

        static::assertSame($shouldMatch, (bool) preg_match("/$pattern/", $subject, $subject));
    }

    public function testNestedMigrationSources(): void
    {
        $a = [
            __DIR__ . '/a/2' => 'My\Test\A1',
            __DIR__ . '/a/3' => 'My\Test\A2',
        ];
        $b = [
            __DIR__ . '/b/1' => 'My\Test\B1',
            __DIR__ . '/b/2' => 'My\Test\B2',
        ];
        $sourceA = new MigrationSource('a', $a);
        $sourceB = new MigrationSource('b', $b);

        $sourceAb = new MigrationSource('ab', [$sourceA, $sourceB]);
        static::assertSame(array_merge($a, $b), $sourceAb->getSourceDirectories());

        $sourceBa = new MigrationSource('ba', [$sourceB, $sourceA]);
        static::assertSame(array_merge($b, $a), $sourceBa->getSourceDirectories());
    }

    public function testNestedMigrationSourcesExtension(): void
    {
        $a = [
            __DIR__ . '/a/2' => 'My\Test\A1',
            __DIR__ . '/a/3' => 'My\Test\A2',
        ];
        $b = [
            __DIR__ . '/b/1' => 'My\Test\B1',
            __DIR__ . '/b/2' => 'My\Test\B2',
        ];
        $sourceA = new MigrationSource('a', $a);
        $sourceB = new MigrationSource('b', $b);

        $sourceAb = new MigrationSource('ab', [$sourceA, $sourceB]);
        static::assertSame(array_merge($a, $b), $sourceAb->getSourceDirectories());

        $aExtension = [
            __DIR__ . '/a/4' => 'My\Test\A4',
        ];
        $sourceA->addDirectory(__DIR__ . '/a/4', $aExtension[__DIR__ . '/a/4']);
        $expected = array_merge($a, $aExtension, $b);
        static::assertSame($expected, $sourceAb->getSourceDirectories());

        $bExtension = [
            __DIR__ . '/b/0' => 'My\Test\A3',
        ];
        $sourceB->addDirectory(__DIR__ . '/b/0', $bExtension[__DIR__ . '/b/0']);
        $expected = array_merge($a, $aExtension, $b, $bExtension);
        static::assertSame($expected, $sourceAb->getSourceDirectories());

        $abExtension = [
            __DIR__ . '/ab/1' => 'My\Test\Ab1',
        ];
        $sourceAb->addDirectory(__DIR__ . '/ab/1', $abExtension[__DIR__ . '/ab/1']);
        $expected = array_merge($a, $aExtension, $b, $bExtension, $abExtension);
        static::assertSame($expected, $sourceAb->getSourceDirectories());
    }

    public function provideCoreRegexData(): array
    {
        return [
            ['Shopware\Core\Migration\Migration1565270366PromotionSetGroupRule', true],
            ['Shopware\Core\Migration\Migration1565346846Promotion', true],
            ['Shopware\Core\Migration\Migration1566293076AddAutoIncrement', true],
            ['Shopware\Core\Migration\Migration1566460168UpdateTexts', true],
            ['Shopware\Core\Migration\Migration1566817701AddDisplayGroup', true],
            ['Shopware\Core\Migration\Migration1567431050ContactFormTemplate', true],
            ['Shopware\Core\Migration\Migration1568120239CmsSection', true],
            ['Shopware\Core\Migration\Migration1568120302CmsBlockUpdate', true],
            ['Shopware\Core\Migration\Migration1568645037AddEnqueueDbal', true],
            ['Shopware\Core\Migration\Migration1568901713PromotionDiscount', true],
            ['Shopware\Core\Migration\Migration1569403146ProductVisibilityUnique', true],
            ['Shopware\Core\Migration\Migration1570187167AddedAppConfig', true],
            ['Shopware\Core\Migration\Migration1570459127AddCmsSidebarLayout', true],
            ['Shopware\Core\Migration\Migration1570621541UpdateDefaultMailTemplates', true],
            ['Shopware\Core\Migration\Migration1570622696CustomerPasswordRecovery', true],
            ['Shopware\Core\Migration\Migration1570629862ClearCategoryBreadcrumbs', true],
            ['Shopware\Core\Migration\Migration1570684913ScheduleIndexer', true],
            ['Shopware\Core\Migration\Migration1571059598ChangeGreatBritainToUnitedKingdom', true],
            ['Shopware\Storefront\Migration\Migration1555406153SalesChannelTheme', true],
            ['Shopware\Storefront\Migration\Migration1563785071AddThemeHelpText', true],
            ['Shopware\Storefront\Migration\Migration1564385954ThemeMedia', true],
            ['Shopware\Storefront\Migration\Migration1564385960ThemeAddActiveFlag', true],
            ['Shopware\Storefront\Migration\Migration1565640170ThemeMigrateMedia', true],
            ['Shopware\Storefront\Migration\Migration1565640175RemoveSalesChannelTheme', true],
            ['Shopware\Storefront\Migration\Migration1568787535AddSeoUrlConstraints', true],
            ['Shopware\Storefront\Migration\Migration1595919251MainCategory', true],
            ['Shopware\Storefront\Migration\Migration1569907970RemoveUnusedSeoColumns', true],
            ['Shopware\Storefront\Migration\Migration1572858066UpdateDefaultCategorySeoUrlTemplate', true],
            ['Shopware\Core\Migration\Something\Migration1571059598ChangeGreatBritainToUnitedKingdom', false],
        ];
    }

    public function provideCoreRegexDataV6_3(): array
    {
        return [
            ['Shopware\Core\Migration\V6_3\Migration1565270366PromotionSetGroupRule', true],
            ['Shopware\Core\Migration\V6_3\Migration1565346846Promotion', true],
            ['Shopware\Core\Migration\V6_3\Migration1566293076AddAutoIncrement', true],
            ['Shopware\Core\Migration\V6_3\Migration1566460168UpdateTexts', true],
            ['Shopware\Core\Migration\V6_3\Migration1566817701AddDisplayGroup', true],
            ['Shopware\Core\Migration\V6_3\Migration1567431050ContactFormTemplate', true],
            ['Shopware\Core\Migration\V6_3\Migration1568120239CmsSection', true],
            ['Shopware\Core\Migration\V6_3\Migration1568120302CmsBlockUpdate', true],
            ['Shopware\Core\Migration\V6_3\Migration1568645037AddEnqueueDbal', true],
            ['Shopware\Core\Migration\V6_3\Migration1568901713PromotionDiscount', true],
            ['Shopware\Core\Migration\V6_3\Migration1569403146ProductVisibilityUnique', true],
            ['Shopware\Core\Migration\V6_3\Migration1570187167AddedAppConfig', true],
            ['Shopware\Core\Migration\V6_3\Migration1570459127AddCmsSidebarLayout', true],
            ['Shopware\Core\Migration\V6_3\Migration1570621541UpdateDefaultMailTemplates', true],
            ['Shopware\Core\Migration\V6_3\Migration1570622696CustomerPasswordRecovery', true],
            ['Shopware\Core\Migration\V6_3\Migration1570629862ClearCategoryBreadcrumbs', true],
            ['Shopware\Core\Migration\V6_3\Migration1570684913ScheduleIndexer', true],
            ['Shopware\Core\Migration\V6_3\Migration1571059598ChangeGreatBritainToUnitedKingdom', true],
            ['Shopware\Storefront\Migration\V6_3\Migration1555406153SalesChannelTheme', true],
            ['Shopware\Storefront\Migration\V6_3\Migration1563785071AddThemeHelpText', true],
            ['Shopware\Storefront\Migration\V6_3\Migration1564385954ThemeMedia', true],
            ['Shopware\Storefront\Migration\V6_3\Migration1564385960ThemeAddActiveFlag', true],
            ['Shopware\Storefront\Migration\V6_3\Migration1565640170ThemeMigrateMedia', true],
            ['Shopware\Storefront\Migration\V6_3\Migration1565640175RemoveSalesChannelTheme', true],
            ['Shopware\Storefront\Migration\V6_3\Migration1568787535AddSeoUrlConstraints', true],
            ['Shopware\Storefront\Migration\V6_3\Migration1595919251MainCategory', true],
            ['Shopware\Storefront\Migration\V6_3\Migration1569907970RemoveUnusedSeoColumns', true],
            ['Shopware\Storefront\Migration\V6_3\Migration1572858066UpdateDefaultCategorySeoUrlTemplate', true],
            ['Shopware\Core\Migration\V6_3\Something\Migration1571059598ChangeGreatBritainToUnitedKingdom', false],
        ];
    }

    public function provideUnitTestData(): array
    {
        return [
            ['__NOPE__', false],
            ['Shopware\Storefront\Migration\Migration1572858066UpdateDefaultCategorySeoUrlTemplate', false],
            ['My\Test\Namespace\Haha', true],
            ['My\Test\Namespace2\Haha', true],
            ['My\Test\Namespace\2\Haha', false],
            ['My\Test\Namespace2\Not\A\Class', false],
        ];
    }
}
