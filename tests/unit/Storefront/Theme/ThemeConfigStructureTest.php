<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\ThemeConfigStructure;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ThemeConfigStructure::class)]
class ThemeConfigStructureTest extends TestCase
{
    public function testGroupsFallBackToDefaultWhenFieldDefinesNone(): void
    {
        $fieldConfig = ['type' => 'color'];

        static::assertSame('default', ThemeConfigStructure::getTab($fieldConfig));
        static::assertSame('default', ThemeConfigStructure::getBlock($fieldConfig));
        static::assertSame('default', ThemeConfigStructure::getSection($fieldConfig));
    }

    public function testGroupsAreTakenFromFieldConfig(): void
    {
        $fieldConfig = ['tab' => 'colorTab', 'block' => 'primaryColors', 'section' => 'home'];

        static::assertSame('colorTab', ThemeConfigStructure::getTab($fieldConfig));
        static::assertSame('primaryColors', ThemeConfigStructure::getBlock($fieldConfig));
        static::assertSame('home', ThemeConfigStructure::getSection($fieldConfig));
    }

    public function testNonStringGroupsFallBackToDefault(): void
    {
        $fieldConfig = ['tab' => null, 'block' => 1, 'section' => ['nested']];

        static::assertSame('default', ThemeConfigStructure::getTab($fieldConfig));
        static::assertSame('default', ThemeConfigStructure::getBlock($fieldConfig));
        static::assertSame('default', ThemeConfigStructure::getSection($fieldConfig));
    }

    public function testSnippetKeysAppendTheirTypeToTheHierarchy(): void
    {
        static::assertSame('colorTab.label', ThemeConfigStructure::buildLabelSnippetKey('colorTab'));
        static::assertSame(
            'colorTab.primaryColors.home.sw-color-primary.label',
            ThemeConfigStructure::buildLabelSnippetKey('colorTab', 'primaryColors', 'home', 'sw-color-primary'),
        );
        static::assertSame(
            'colorTab.primaryColors.home.sw-color-primary.helpText',
            ThemeConfigStructure::buildHelpTextSnippetKey('colorTab', 'primaryColors', 'home', 'sw-color-primary'),
        );
    }
}
