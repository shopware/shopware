<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Migration;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Migration\MigrationSource;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class MigrationSourceTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @dataProvider provideCoreRegexData
     */
    public function testCoreRegex(string $subject, bool $shoulMatch): void
    {
        $pattern = $this->getContainer()->get('Shopware\Core\Framework\Migration\MigrationSource.core')->getNamespacePattern();

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
