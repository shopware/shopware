<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Migration;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Migration\MigrationSource;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_3\Migration1565270366PromotionSetGroupRule;
use Shopware\Core\Migration\V6_3\Migration1565346846Promotion;
use Shopware\Core\Migration\V6_3\Migration1566293076AddAutoIncrement;
use Shopware\Core\Migration\V6_3\Migration1566460168UpdateTexts;
use Shopware\Core\Migration\V6_3\Migration1566817701AddDisplayGroup;
use Shopware\Core\Migration\V6_3\Migration1567431050ContactFormTemplate;
use Shopware\Core\Migration\V6_3\Migration1568120239CmsSection;
use Shopware\Core\Migration\V6_3\Migration1568120302CmsBlockUpdate;
use Shopware\Core\Migration\V6_3\Migration1568645037AddEnqueueDbal;
use Shopware\Core\Migration\V6_3\Migration1568901713PromotionDiscount;
use Shopware\Core\Migration\V6_3\Migration1569403146ProductVisibilityUnique;
use Shopware\Core\Migration\V6_3\Migration1570187167AddedAppConfig;
use Shopware\Core\Migration\V6_3\Migration1570459127AddCmsSidebarLayout;
use Shopware\Core\Migration\V6_3\Migration1570621541UpdateDefaultMailTemplates;
use Shopware\Core\Migration\V6_3\Migration1570622696CustomerPasswordRecovery;
use Shopware\Core\Migration\V6_3\Migration1570629862ClearCategoryBreadcrumbs;
use Shopware\Core\Migration\V6_3\Migration1570684913ScheduleIndexer;
use Shopware\Core\Migration\V6_3\Migration1571059598ChangeGreatBritainToUnitedKingdom;

/**
 * @internal
 */
class MigrationSourceTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @dataProvider provideCoreRegexDataV6_3
     */
    public function testCoreRegexV63(string $subject, bool $shouldMatch): void
    {
        $pattern = $this->getContainer()->get('Shopware\Core\Framework\Migration\MigrationSource.core.V6_3')->getNamespacePattern();

        static::assertSame($shouldMatch, (bool) preg_match("/$pattern/", $subject, $subject));
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

    public static function provideCoreRegexDataV6_3(): array
    {
        $cases = [
            [Migration1565270366PromotionSetGroupRule::class, true],
            [Migration1565346846Promotion::class, true],
            [Migration1566293076AddAutoIncrement::class, true],
            [Migration1566460168UpdateTexts::class, true],
            [Migration1566817701AddDisplayGroup::class, true],
            [Migration1567431050ContactFormTemplate::class, true],
            [Migration1568120239CmsSection::class, true],
            [Migration1568120302CmsBlockUpdate::class, true],
            [Migration1568645037AddEnqueueDbal::class, true],
            [Migration1568901713PromotionDiscount::class, true],
            [Migration1569403146ProductVisibilityUnique::class, true],
            [Migration1570187167AddedAppConfig::class, true],
            [Migration1570459127AddCmsSidebarLayout::class, true],
            [Migration1570621541UpdateDefaultMailTemplates::class, true],
            [Migration1570622696CustomerPasswordRecovery::class, true],
            [Migration1570629862ClearCategoryBreadcrumbs::class, true],
            [Migration1570684913ScheduleIndexer::class, true],
            [Migration1571059598ChangeGreatBritainToUnitedKingdom::class, true],
            ['Shopware\Core\Migration\V6_3\Something\Migration1571059598ChangeGreatBritainToUnitedKingdom', false],
        ];

        return $cases;
    }

    public static function provideUnitTestData(): array
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
